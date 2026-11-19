<?php
declare(strict_types=1);

/**
 * SSMS Mail Service
 * Sends email using configured SMTP settings (settings group 'smtp') with fallback
 * to PHP mail(). Designed to drop in PHPMailer via Composer later.
 */
final class MailService
{
    public static function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        $schoolId = school_id();
        $host = (string)Setting::get($schoolId, 'smtp.smtp_host', '');
        $port = (int)Setting::get($schoolId, 'smtp.smtp_port', 587);
        $user = (string)Setting::get($schoolId, 'smtp.smtp_username', '');
        $pass = (string)Setting::get($schoolId, 'smtp.smtp_password', '');
        $enc  = (string)Setting::get($schoolId, 'smtp.smtp_encryption', 'tls');
        $fromName = (string)Setting::get($schoolId, 'smtp.smtp_from_name', APP_NAME);
        $fromAddr = (string)Setting::get($schoolId, 'smtp.smtp_from_address', 'no-reply@localhost');

        $h = [];
        $h[] = 'From: '.$fromName.' <'.$fromAddr.'>';
        $h[] = 'Reply-To: '.$fromAddr;
        $h[] = 'X-Mailer: SSMS/1.0';
        $h[] = 'MIME-Version: 1.0';
        $h[] = 'Content-Type: text/html; charset=UTF-8';
        foreach ($headers as $k => $v) { $h[] = $k.': '.$v; }

        if ($host !== '' && function_exists('fsockopen')) {
            return self::sendSmtp($to, $subject, $body, $host, $port, $user, $pass, $enc, $fromName, $fromAddr, implode("\r\n", $h));
        }
        return @mail($to, $subject, $body, implode("\r\n", $h));
    }

    private static function sendSmtp(string $to, string $subject, string $body, string $host, int $port, string $user, string $pass, string $enc, string $fromName, string $fromAddr, string $headers): bool
    {
        // Prevent header injection before any address reaches an SMTP command.
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $fromAddr = str_replace(["\r", "\n"], '', $fromAddr);
        $fromName = str_replace(["\r", "\n"], ' ', $fromName);

        $smtp = @fsockopen(
            ($enc === 'ssl' ? 'ssl://' : '') . $host,
            $port,
            $errno, $errstr, 10
        );
        if (!$smtp) { return false; }
        stream_set_timeout($smtp, 10);

        $read = static function () use ($smtp): string {
            $data = '';
            while (($line = fgets($smtp, 512)) !== false) {
                $data .= $line;
                // "250-..." continues a multi-line reply; "250 ..." is the final line.
                if (strlen($line) < 4 || $line[3] !== '-') { break; }
            }
            return $data;
        };
        $code = static fn (string $reply): string => substr(trim($reply), 0, 3);
        $cmd = static function (string $command) use ($smtp, $read): string {
            fwrite($smtp, $command . "\r\n");
            return $read();
        };

        try {
            if ($code($read()) !== '220') { return false; }

            $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
            if ($code($cmd('EHLO ' . $ehloHost)) !== '250') {
                if ($code($cmd('HELO ' . $ehloHost)) !== '250') { return false; }
            }

            if ($enc === 'tls') {
                if ($code($cmd('STARTTLS')) !== '220') { return false; }
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!@stream_socket_enable_crypto($smtp, true, $crypto)) { return false; }
                if ($code($cmd('EHLO ' . $ehloHost)) !== '250') { return false; }
            }

            if ($user !== '') {
                if ($code($cmd('AUTH LOGIN')) !== '334') { return false; }
                if ($code($cmd(base64_encode($user))) !== '334') { return false; }
                if ($code($cmd(base64_encode($pass))) !== '235') { return false; }
            }

            if ($code($cmd('MAIL FROM: <' . $fromAddr . '>')) !== '250') { return false; }
            if (!in_array($code($cmd('RCPT TO: <' . $to . '>')), ['250', '251'], true)) { return false; }
            if ($code($cmd('DATA')) !== '354') { return false; }

            // Prevent SMTP dot-termination (a line consisting of "." ends DATA).
            $body = preg_replace('/(?m)^\./', '..', $body) ?? $body;

            $msg = "From: " . $fromName . " <" . $fromAddr . ">\r\n"
                . "To: " . $to . "\r\n"
                . "Subject: " . $subject . "\r\n"
                . $headers . "\r\n\r\n"
                . $body . "\r\n.";
            if (fwrite($smtp, $msg . "\r\n") === false) { return false; }
            if ($code($read()) !== '250') { return false; }

            $cmd('QUIT');
            return true;
        } finally {
            fclose($smtp);
        }
    }

    public static function notification(string $to, string $subject, string $body): bool
    {
        return self::send($to, $subject, $body);
    }
}
