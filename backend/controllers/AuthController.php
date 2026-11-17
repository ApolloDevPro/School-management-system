<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    public function install(): void
    {
        if (Auth::check()) { redirect('dashboard'); }
        $pending = User::withoutPassword();
        if (!$pending) { redirect('login'); }
        $this->render('auth/install', ['pageTitle' => 'First-time setup', 'pending' => $pending], 'bare');
    }

    public function saveInstall(): void
    {
        $pending = User::withoutPassword();
        if (!$pending) { $this->respond(false, 'Every account already has a password. Sign in instead.', 'login'); }
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');
        $errors = [];
        $target = null;
        foreach ($pending as $row) { if ($row['username'] === $username) { $target = $row; break; } }
        if ($target === null) { $errors['username'] = 'Choose an account that still needs a password.'; }
        if (strlen($password) < 8) { $errors['password'] = 'Use at least 8 characters.'; }
        if ($password !== $confirm) { $errors['confirm'] = 'The two passwords do not match.'; }
        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'install', [], $errors); }
        User::setPassword((int)$target['id'], $password);
        audit('security', 'install', 'users', (int)$target['id'], 'Initial password set for ' . $target['username']);
        $this->respond(true, 'Password set for ' . $target['username'] . '. You can now sign in.', 'login');
    }

    public function loginForm(): void
    {
        if (Auth::check()) { redirect('dashboard'); }
        unset($_SESSION['pending_2fa']);
        $this->render('auth/login', ['pageTitle' => 'Sign in', 'school' => school_row()], 'bare');
    }

    public function login(): void
    {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') { $this->respond(false, 'Enter your username and password.', 'login'); }
        [$ok, $message, $target] = Auth::attempt($username, $password);
        if (!$ok) {
            audit('security', 'login_failed', 'users', null, 'Failed sign-in attempt for "' . $username . '"');
            $this->respond(false, $message, 'login');
        }
        $redirect = $target === 'two_factor' ? 'two-factor/challenge' : ($target ?: 'dashboard');
        $this->respond(true, $message, $redirect);
    }

    public function twoFactorChallenge(): void
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending || ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa']);
            flash_set('warning', 'Your verification session expired. Sign in again.');
            redirect('login');
        }
        $this->render('auth/two_factor', [
            'pageTitle' => 'Two-factor verification',
            'school' => school_row(),
            'username' => (User::find((int)$pending['user_id'])['username'] ?? ''),
        ], 'bare');
    }

    public function twoFactorVerify(): void
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending || ($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa']);
            $this->respond(false, 'Verification session expired. Sign in again.', 'login');
        }
        $code = trim((string)($_POST['code'] ?? ''));
        $userId = (int)$pending['user_id'];
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            $this->respond(false, 'Enter the 6-digit code from your authenticator app.', 'two-factor/challenge');
        }
        $secretRow = Database::conn()->prepare(
            "SELECT code_hash FROM two_factor_codes WHERE user_id=? AND channel='authenticator' AND verified_at IS NOT NULL ORDER BY id DESC LIMIT 1"
        );
        $secretRow->execute([$userId]);
        $secret = (string)($secretRow->fetchColumn() ?: '');
        if ($secret === '' || !TwoFactorController::verifyTotpStatic($secret, $code)) {
            audit('security', '2fa_failed', 'users', $userId, 'Invalid 2FA code during login');
            $this->respond(false, 'Invalid verification code.', 'two-factor/challenge');
        }
        if (!Auth::completePendingTwoFactor($userId)) {
            $this->respond(false, 'Verification session expired. Sign in again.', 'login');
        }
        audit('security', '2fa_verified', 'users', $userId, 'Completed 2FA login challenge');
        $target = Auth::mustChangePassword() ? 'change-password' : 'dashboard';
        $this->respond(true, 'Verification successful.', $target);
    }

    public function forgotPasswordForm(): void
    {
        if (Auth::check()) { redirect('dashboard'); }
        $this->render('auth/forgot', ['pageTitle' => 'Forgot password', 'school' => school_row()], 'bare');
    }

    public function forgotPassword(): void
    {
        if (Auth::check()) { redirect('dashboard'); }
        $identity = trim((string)($_POST['identity'] ?? ''));
        $user = $identity !== '' ? User::findForPasswordReset($identity) : null;
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            Database::conn()->prepare('DELETE FROM password_resets WHERE user_id=? AND is_used=0')->execute([(int)$user['id']]);
            Database::conn()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, channel, requested_ip, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
            )->execute([(int)$user['id'], $hash, ($user['email'] ?? '') ? 'email' : 'admin', client_ip()]);
            $link = url('reset-password', ['token' => $token]);
            $sent = false;
            if (!empty($user['email'])) {
                $body = '<p>A password reset was requested for your SSMS account.</p><p><a href="' . e($link) . '">Reset your password</a></p><p>This link expires in 30 minutes. If you did not request this, you can ignore this email.</p>';
                $sent = MailService::send($user['email'], 'SSMS Password Reset', $body);
            }
            audit('security', 'password_reset_requested', 'users', (int)$user['id'], 'Password reset requested');
            if (!$sent) {
                $_SESSION['reset_link_once'] = $link;
            }
        }
        $msg = 'If the account exists, password reset instructions have been prepared.';
        if (!empty($_SESSION['reset_link_once'])) { $msg .= ' Email delivery is not configured; use the link shown on the next screen.'; }
        flash_set('success', $msg);
        redirect('forgot-password', ['sent' => 1]);
    }

    public function resetPasswordForm(): void
    {
        if (Auth::check()) { redirect('dashboard'); }
        $token = trim((string)($_GET['token'] ?? ''));
        $valid = $token !== '' ? User::validPasswordReset($token) : null;
        $link = $_SESSION['reset_link_once'] ?? null;
        unset($_SESSION['reset_link_once']);
        $this->render('auth/reset', [
            'pageTitle' => 'Reset password',
            'school' => school_row(),
            'token' => $token,
            'valid' => (bool)$valid,
            'devLink' => $link,
        ], 'bare');
    }

    public function resetPassword(): void
    {
        if (Auth::check()) { redirect('dashboard'); }
        $token = trim((string)($_POST['token'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');
        $reset = $token !== '' ? User::validPasswordReset($token) : null;
        $errors = [];
        if (!$reset) { $errors['token'] = 'This reset link is invalid or has expired.'; }
        if (strlen($password) < 8) { $errors['password'] = 'Use at least 8 characters.'; }
        if ($password !== $confirm) { $errors['confirm'] = 'The two passwords do not match.'; }
        if ($errors) { $this->respond(false, $errors['token'] ?? 'Please correct the highlighted fields.', 'reset-password', ['token' => $token], $errors); }
        User::applyPasswordReset((int)$reset['id'], (int)$reset['user_id'], $password);
        audit('security', 'password_reset', 'users', (int)$reset['user_id'], 'Password reset via token');
        $this->respond(true, 'Password updated. You can now sign in.', 'login');
    }

    public function changePasswordForm(): void
    {
        $this->render('auth/change_password', [
            'pageTitle' => 'Change password',
            'activeNav' => 'account',
            'mustChange' => Auth::mustChangePassword(),
        ], 'bare');
    }

    public function changePassword(): void
    {
        $user = Auth::user();
        if (!$user) { redirect('login'); }
        $current = (string)($_POST['current_password'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');
        $row = User::find((int)$user['id']);
        $errors = [];
        if (!$row || !password_verify($current, (string)$row['password_hash'])) { $errors['current_password'] = 'Current password is incorrect.'; }
        if (strlen($password) < 8) { $errors['password'] = 'Use at least 8 characters.'; }
        if ($password !== $confirm) { $errors['confirm'] = 'The two passwords do not match.'; }
        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'change-password', [], $errors); }
        User::changeOwnPassword((int)$user['id'], $password);
        Auth::markPasswordChanged();
        audit('security', 'password_changed', 'users', (int)$user['id'], 'User changed own password');
        $this->respond(true, 'Password changed successfully.', 'dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        flash_set('success', 'You have been signed out.');
        redirect('login');
    }
}
