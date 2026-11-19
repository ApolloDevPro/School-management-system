<?php
declare(strict_types=1);

/**
 * TOTP authenticator setup, verification and disabling.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class TwoFactorController extends Controller
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function setup(): void
    {
        if (!$this->allowed('security', 'edit')) {
            return;
        }
        $userId = (int)Auth::user()['id'];
        $secret = $this->generateSecret();
        $issuer = 'SSMS';
        $account = Auth::user()['username'] ?? 'user';
        $otpAuthUrl = 'otpauth://totp/' . urlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . urlencode($issuer);

        $this->render('two_factor/setup', [
            'pageTitle' => 'Two-Factor Authentication Setup',
            'activeNav' => 'security',
            'secret' => $secret,
            'otpAuthUrl' => $otpAuthUrl,
        ]);
    }

    public function verifySetup(): void
    {
        if (!$this->allowed('security', 'edit')) { return; }
        $userId = (int)Auth::user()['id'];
        $code = trim((string)($_POST['code'] ?? ''));
        $secret = trim((string)($_POST['secret'] ?? ''));

        if ($code === '' || $secret === '') {
            $this->respond(false, 'Code and secret are required.', 'security/2fa-setup');
        }
        if (!self::verifyTotpStatic($secret, $code)) {
            $this->respond(false, 'Invalid verification code. Try again.', 'security/2fa-setup');
        }

        Database::conn()->prepare("DELETE FROM two_factor_codes WHERE user_id = ? AND channel = 'authenticator'")->execute([$userId]);
        Database::conn()->prepare(
            "INSERT INTO two_factor_codes (user_id, channel, code_hash, destination, expires_at, verified_at, is_used)
             VALUES (?, 'authenticator', ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), 1)"
        )->execute([$userId, $secret, $userId]);

        audit('security', 'enable', 'two_factor_codes', null, 'Enabled authenticator 2FA for user ' . $userId);
        $this->respond(true, 'Two-factor authentication enabled using authenticator app.', 'security');
    }

    public function disable(): void
    {
        if (!$this->allowed('security', 'edit')) { return; }
        $userId = (int)Auth::user()['id'];
        Database::conn()->prepare("DELETE FROM two_factor_codes WHERE user_id = ? AND channel = 'authenticator'")->execute([$userId]);
        audit('security', 'disable', 'two_factor_codes', null, 'Disabled authenticator 2FA for user ' . $userId);
        $this->respond(true, 'Two-factor authentication disabled.', 'security');
    }

    public function sendCode(): void
    {
        if (!$this->allowed('security', 'edit')) { return; }
        $userId = (int)Auth::user()['id'];
        $user = Auth::user();
        $channel = (string)($_POST['channel'] ?? 'sms');

        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = hash('sha256', $code);

        $destination = '';
        if ($channel === 'sms') { $destination = $user['phone'] ?? ''; }
        elseif ($channel === 'email') { $destination = $user['email'] ?? ''; }

        if ($destination === '') {
            $this->respond(false, 'No ' . $channel . ' address on file for your account.', 'security');
        }

        Database::conn()->prepare('DELETE FROM two_factor_codes WHERE user_id = ? AND channel = ?')->execute([$userId, $channel]);
        Database::conn()->prepare(
            'INSERT INTO two_factor_codes (user_id, channel, code_hash, destination, max_attempts, issued_at, expires_at, is_used)
             VALUES (?, ?, ?, ?, 5, NOW(), DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0)'
        )->execute([$userId, $channel, $codeHash, $destination]);

        $sent = false;
        if ($channel === 'sms') {
            $result = SmsService::send($destination, 'Your SSMS verification code is ' . $code . '. It expires in 10 minutes.');
            $sent = $result['ok'] ?? false;
        } elseif ($channel === 'email') {
            $sent = MailService::send($destination, 'SSMS Verification Code', 'Your verification code is <strong>' . $code . '</strong>. It expires in 10 minutes.');
        }

        if ($sent) { $this->respond(true, 'Verification code sent via ' . $channel . '.', 'security'); }
        else { $this->respond(false, 'Failed to send code via ' . $channel . '.', 'security'); }
    }

    public function verifyCode(): void
    {
        if (!$this->allowed('security', 'edit')) { return; }
        $userId = (int)Auth::user()['id'];
        $channel = (string)($_POST['channel'] ?? 'sms');
        $code = trim((string)($_POST['code'] ?? ''));

        if ($code === '') { $this->respond(false, 'Enter the verification code.', 'security'); }

        $stmt = Database::conn()->prepare(
            'SELECT * FROM two_factor_codes WHERE user_id = ? AND channel = ? AND is_used = 0 AND expires_at > NOW() ORDER BY issued_at DESC LIMIT 1'
        );
        $stmt->execute([$userId, $channel]);
        $row = $stmt->fetch();

        if (!$row) { $this->respond(false, 'No valid verification code found. Request a new one.', 'security'); }

        $attempts = (int)($row['attempts'] ?? 0) + 1;
        $maxAttempts = (int)($row['max_attempts'] ?? 5);
        if ($attempts > $maxAttempts) {
            Database::conn()->prepare('DELETE FROM two_factor_codes WHERE id = ?')->execute([(int)$row['id']]);
            $this->respond(false, 'Too many failed attempts. Code expired. Request a new one.', 'security');
        }

        $valid = hash_equals((string)$row['code_hash'], hash('sha256', $code));
        Database::conn()->prepare('UPDATE two_factor_codes SET attempts = ?, is_used = IF(?, 1, is_used) WHERE id = ?')->execute([$attempts, $valid ? 1 : 0, (int)$row['id']]);
        if (!$valid) {
            $this->respond(false, 'Invalid verification code. Attempt ' . $attempts . ' of ' . $maxAttempts . '.', 'security');
        }
        Database::conn()->prepare('UPDATE two_factor_codes SET verified_at = NOW(), is_used = 1 WHERE id = ?')->execute([(int)$row['id']]);
        audit('security', 'verify', 'two_factor_codes', null, 'Verified ' . $channel . ' 2FA code for user ' . $userId);
        $this->respond(true, 'Code verified.', 'security');
    }

    private function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) { $secret .= self::BASE32[random_int(0, 31)]; }
        return $secret;
    }

    public static function verifyTotpStatic(string $secret, string $code, int $window = 1): bool
    {
        $key = self::base32DecodeStatic($secret);
        if ($key === '') { return false; }
        $time = (int)floor(time() / 30);
        $code = str_pad(preg_replace('/\D/', '', $code) ?? '', 6, '0', STR_PAD_LEFT);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::generateTotpStatic($key, $time + $offset), $code)) { return true; }
        }
        return false;
    }

    public static function base32DecodeStatic(string $secret): string
    {
        $secret = strtoupper(trim($secret));
        $binary = '';
        $base32 = self::BASE32;
        for ($i = 0; $i < strlen($secret); $i++) {
            $pos = strpos($base32, $secret[$i]);
            if ($pos === false) { continue; }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $result = '';
        for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
            $result .= chr((int)bindec(substr($binary, $i, 8)));
        }
        return $result;
    }

    public static function generateTotpStatic(string $key, int $time): string
    {
        $timeBytes = pack('N2', 0, $time);
        $hash = hash_hmac('sha1', $timeBytes, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binary = substr($hash, $offset, 4);
        $value = unpack('N', $binary)[1] & 0x7FFFFFFF;
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}