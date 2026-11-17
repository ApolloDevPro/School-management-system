<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(string $username, string $password): array
    {
        $rows = User::findLoginsByUsername($username);
        if (!$rows) {
            return [false, 'Invalid username or password.', null];
        }

        $first = $rows[0];

        if ($first['locked_until'] !== null && strtotime($first['locked_until']) > time()) {
            return [false, 'Account locked until ' . date('d M Y, H:i', strtotime($first['locked_until'])) . '.', null];
        }

        $matched = null;
        foreach ($rows as $row) {
            if ($row['password_hash'] !== null && password_verify($password, $row['password_hash'])) {
                $matched = $row;
                break;
            }
        }

        if ($matched === null) {
            if ($first['password_hash'] === null) {
                return [false, 'No password is set for this account yet. Open the Install page to set one.', null];
            }
            $limit = (int)Setting::get((int)$first['school_id'], 'security.max_failed_logins', 5);
            User::recordFailure((int)$first['id'], $limit);
            return [false, 'Invalid username or password.', null];
        }

        if (!(int)$matched['is_active']) {
            return [false, 'This account is deactivated. Contact the administrator.', null];
        }

        if (self::hasTwoFactor((int)$matched['id'])) {
            User::recordSuccess((int)$matched['id']);
            session_regenerate_id(true);
            $_SESSION['pending_2fa'] = [
                'user_id' => (int)$matched['id'],
                'school_id' => (int)$matched['school_id'],
                'expires_at' => time() + 600,
            ];
            return [true, 'Enter your verification code to continue.', 'two_factor'];
        }

        self::login($matched);
        $target = ((int)$matched['must_change_password'] === 1) ? 'change-password' : 'dashboard';
        return [true, 'Welcome back, ' . $matched['full_name'] . '.', $target];
    }

    public static function hasTwoFactor(int $userId): bool
    {
        $row = Database::conn()->prepare(
            "SELECT id FROM two_factor_codes WHERE user_id = ? AND channel = 'authenticator' AND verified_at IS NOT NULL ORDER BY id DESC LIMIT 1"
        );
        $row->execute([$userId]);
        return (bool)$row->fetchColumn();
    }

    public static function completePendingTwoFactor(int $userId): bool
    {
        $pending = $_SESSION['pending_2fa'] ?? null;
        if (!$pending || (int)($pending['user_id'] ?? 0) !== $userId) {
            return false;
        }
        if (($pending['expires_at'] ?? 0) < time()) {
            unset($_SESSION['pending_2fa']);
            return false;
        }
        $user = User::find($userId);
        if (!$user) {
            unset($_SESSION['pending_2fa']);
            return false;
        }
        unset($_SESSION['pending_2fa']);
        self::login($user);
        return true;
    }

    public static function loginUserRow(array $user): void
    {
        self::login($user);
    }

    private static function login(array $user): void
    {
        User::recordSuccess((int)$user['id']);

        $role = null;
        if (!empty($user['role_id'])) {
            $role = Role::find((int)$user['role_id']);
        }

        session_regenerate_id(true);

        // Regenerate CSRF token to prevent session fixation
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));

        $_SESSION['user'] = [
            'id'                   => (int)$user['id'],
            'school_id'            => (int)$user['school_id'],
            'username'             => $user['username'],
            'full_name'            => $user['full_name'],
            'user_type'            => $user['user_type'],
            'role_id'              => $role ? (int)$role['id'] : null,
            'role_name'            => $role['name'] ?? 'No role assigned',
            'role_slug'            => $role['slug'] ?? '',
            'must_change_password' => (int)$user['must_change_password'],
            'perms'                => $role ? Role::permissionsFor((int)$role['id']) : [],
        ];

        audit('security', 'login', 'users', (int)$user['id'], 'Signed in to the system');
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function mustChangePassword(): bool
    {
        return self::check() && (int)(self::user()['must_change_password'] ?? 0) === 1;
    }

    public static function markPasswordChanged(): void
    {
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['must_change_password'] = 0;
        }
    }

    public static function can(string $module, string $action = 'view'): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        return (bool)($user['perms'][$module][$action] ?? false);
    }

    public static function logout(): void
    {
        if (self::check()) {
            audit('security', 'logout', 'users', (int)$_SESSION['user']['id'], 'Signed out of the system');
        }
        // Regenerate CSRF token before destroying session
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
