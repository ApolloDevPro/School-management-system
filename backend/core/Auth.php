<?php
declare(strict_types=1);

/**
 * Authentication and the signed-in user's permission snapshot.
 *
 * Permissions are loaded once at login and cached in $_SESSION['user']['perms'],
 * so role changes take effect at the user's next sign-in.
 */
final class Auth
{
    /** Session lifetime in seconds, matching the cookie set in index.php. */
    private const SESSION_LIFETIME = 14400;

    /**
     * Verify credentials for a username.
     *
     * Usernames are unique per school, so several rows can share a username;
     * each password is tested until one matches. Returns [ok, message, target]
     * where target is a route name, 'two_factor', or null on failure.
     */
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
            $_SESSION['pending_2fa_attempts'] = 0;
            return [true, 'Enter your verification code to continue.', 'two_factor'];
        }

        self::login($matched);
        if ((int)$matched['must_change_password'] === 1) {
            $target = 'change-password';
        } elseif (in_array($matched['user_type'] ?? '', ['parent', 'student'], true)) {
            $target = 'portal';
        } else {
            $target = 'dashboard';
        }
        return [true, 'Welcome back, ' . $matched['full_name'] . '.', $target];
    }

    /**
     * Landing route for the signed-in account. Portal users go to the portal
     * because staff modules are closed to them.
     */
    public static function homeRoute(): string
    {
        $user = self::user();
        return ($user && in_array($user['user_type'] ?? '', ['parent', 'student'], true))
            ? 'portal'
            : 'dashboard';
    }

    /**
     * Create the server-side record for this sign-in and remember its token.
     *
     * The raw token stays in the PHP session; only a SHA-256 hash is stored, so
     * the session list can identify a device without exposing usable tokens.
     */
    private static function registerSession(array $user, string $roleSlug = ''): void
    {
        try {
            self::closeTrackedSession('concurrent');

            $token = bin2hex(random_bytes(32));
            $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $lifetime = (int)(ini_get('session.gc_maxlifetime') ?: self::SESSION_LIFETIME);
            Database::conn()->prepare(
                'INSERT INTO portal_sessions
                    (user_id, session_token_hash, ip_address, user_agent, device_type,
                     portal_type, started_at, last_activity_at, expires_at, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), 1)'
            )->execute([
                (int)$user['id'],
                hash('sha256', $token),
                mb_substr(client_ip(), 0, 45),
                mb_substr($agent, 0, 255),
                self::deviceType($agent),
                self::portalType((string)($user['user_type'] ?? 'staff'), $roleSlug),
                $lifetime,
            ]);
            $_SESSION['session_token'] = $token;
        } catch (Throwable) {
            // Tracking is best effort; a missing table must never block sign-in.
            unset($_SESSION['session_token']);
        }
    }

    /**
     * Mark the record held by this browser as closed.
     */
    private static function closeTrackedSession(string $reason): void
    {
        $token = (string)($_SESSION['session_token'] ?? '');
        if ($token === '') {
            return;
        }
        Database::conn()->prepare(
            'UPDATE portal_sessions
                SET is_active = 0, logout_at = NOW(), logout_reason = ?
              WHERE session_token_hash = ? AND is_active = 1'
        )->execute([$reason, hash('sha256', $token)]);
        unset($_SESSION['session_token']);
    }

    /**
     * Check the signed-in session against the server-side record and refresh
     * its activity stamp. Returns null while the session is valid, or the
     * message to show before sending the user back to the login page.
     */
    public static function guardSession(): ?string
    {
        $user = self::user();
        if (!$user) {
            return null;
        }

        try {
            $token = (string)($_SESSION['session_token'] ?? '');
            if ($token === '') {
                // Adopt sessions started before tracking existed.
                self::registerSession($user, (string)($user['role_slug'] ?? ''));
                return null;
            }

            $statement = Database::conn()->prepare(
                'SELECT id, is_active, expires_at, logout_reason
                   FROM portal_sessions
                  WHERE session_token_hash = ?
                  ORDER BY id DESC LIMIT 1'
            );
            $statement->execute([hash('sha256', $token)]);
            $row = $statement->fetch();

            if (!$row) {
                // The record was pruned; recreate it rather than lock the user out.
                self::registerSession($user, (string)($user['role_slug'] ?? ''));
                return null;
            }

            if (!(int)$row['is_active']) {
                $message = match ((string)$row['logout_reason']) {
                    'admin'           => 'An administrator ended your session. Sign in again.',
                    'password_change' => 'Your password changed. Sign in again.',
                    'concurrent'      => 'This session was replaced by a newer sign-in.',
                    default           => 'Your session ended. Sign in again.',
                };
                self::clearIdentity();
                return $message;
            }

            if (strtotime((string)$row['expires_at']) <= time()) {
                Database::conn()->prepare(
                    "UPDATE portal_sessions
                        SET is_active = 0, logout_at = NOW(), logout_reason = 'timeout'
                      WHERE id = ?"
                )->execute([(int)$row['id']]);
                self::clearIdentity();
                return 'Your session expired. Sign in again.';
            }

            // Refresh the activity stamp at most once a minute.
            Database::conn()->prepare(
                'UPDATE portal_sessions SET last_activity_at = NOW()
                  WHERE id = ? AND last_activity_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)'
            )->execute([(int)$row['id']]);
        } catch (Throwable) {
            // Never lock people out because the tracking table is unavailable.
        }

        return null;
    }

    /**
     * Drop the signed-in identity but keep the PHP session alive so the login
     * page can show the reason for the ending.
     */
    private static function clearIdentity(): void
    {
        unset($_SESSION['user'], $_SESSION['session_token'], $_SESSION['pending_2fa'], $_SESSION['pending_2fa_attempts']);
        session_regenerate_id(true);
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    /**
     * Coarse device class from the browser's user agent string.
     */
    private static function deviceType(string $agent): string
    {
        $agent = strtolower($agent);
        if ($agent === '') {
            return 'unknown';
        }
        if (preg_match('/ipad|tablet|kindle|playbook|silk|android(?!.*mobile)/', $agent)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/', $agent)) {
            return 'mobile';
        }
        return 'desktop';
    }

    /**
     * Portal type stored with the session: admin, staff, parent or student.
     */
    private static function portalType(string $userType, string $roleSlug): string
    {
        return match ($userType) {
            'parent'  => 'parent',
            'student' => 'student',
            default   => $roleSlug === 'administrator' ? 'admin' : 'staff',
        };
    }

    /**
     * True when the user has a verified authenticator app enrolled.
     */
    public static function hasTwoFactor(int $userId): bool
    {
        $row = Database::conn()->prepare(
            "SELECT id FROM two_factor_codes WHERE user_id = ? AND channel = 'authenticator' AND verified_at IS NOT NULL ORDER BY id DESC LIMIT 1"
        );
        $row->execute([$userId]);
        return (bool)$row->fetchColumn();
    }

    /**
     * Finish a 2FA login after the code was verified, provided the pending
     * challenge still matches the user and has not expired.
     */
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
        if (!$user || !(int)$user['is_active']) {
            unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_attempts']);
            return false;
        }
        unset($_SESSION['pending_2fa'], $_SESSION['pending_2fa_attempts']);
        self::login($user);
        return true;
    }

    public static function loginUserRow(array $user): void
    {
        self::login($user);
    }

    /**
     * Establish the session: refresh the login record, regenerate the session
     * id and CSRF token, then snapshot the user and their permissions.
     */
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

        self::registerSession($user, (string)($role['slug'] ?? ''));

        $_SESSION['user'] = [
            'id'                   => (int)$user['id'],
            'school_id'            => (int)$user['school_id'],
            'username'             => $user['username'],
            'full_name'            => $user['full_name'],
            'user_type'            => $user['user_type'],
            'email'                => $user['email'] ?? null,
            'phone'                => $user['phone'] ?? null,
            'staff_id'             => !empty($user['staff_id']) ? (int)$user['staff_id'] : null,
            'guardian_id'          => !empty($user['guardian_id']) ? (int)$user['guardian_id'] : null,
            'student_id'           => !empty($user['student_id']) ? (int)$user['student_id'] : null,
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

    /**
     * Permission check against the cached matrix, e.g. can('finance', 'edit').
     */
    public static function can(string $module, string $action = 'view'): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        return (bool)($user['perms'][$module][$action] ?? false);
    }

    /**
     * Audit, clear the session and expire its cookie.
     */
    public static function logout(): void
    {
        if (self::check()) {
            audit('security', 'logout', 'users', (int)$_SESSION['user']['id'], 'Signed out of the system');
            try {
                self::closeTrackedSession('user');
            } catch (Throwable) {
                // Tracking table unavailable; the local session is still cleared.
            }
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
