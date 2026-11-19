<?php
declare(strict_types=1);

/**
 * User accounts, login lookup and password handling.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class User extends Model
{
    protected static string $table = 'users';

    public static function findLoginsByUsername(string $username): array
    {
        return static::query('SELECT * FROM users WHERE username = ? ORDER BY id', [$username]);
    }

    public static function withoutPassword(): array
    {
        return static::query(
            'SELECT id, username, full_name FROM users WHERE password_hash IS NULL ORDER BY id'
        );
    }

    public static function allWithRole(int $schoolId): array
    {
        return static::query(
            'SELECT u.id, u.username, u.full_name, u.email, u.phone, u.user_type, u.role_id,
                    u.staff_id, u.guardian_id, u.student_id,
                    u.is_active, u.must_change_password, u.failed_attempts, u.locked_until,
                    u.last_login_at, u.last_login_ip, r.name AS role_name,
                    CONCAT(s.first_name, \' \', s.last_name) AS staff_name,
                    CONCAT(g.first_name, \' \', g.last_name) AS guardian_name,
                    CONCAT(st.first_name, \' \', st.last_name) AS student_name
               FROM users u
               LEFT JOIN roles r ON r.id = u.role_id
               LEFT JOIN staff s ON s.id = u.staff_id
               LEFT JOIN guardians g ON g.id = u.guardian_id
               LEFT JOIN students st ON st.id = u.student_id
              WHERE u.school_id = ?
              ORDER BY u.full_name',
            [$schoolId]
        );
    }

    public static function usernameTaken(string $username, int $schoolId, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE school_id = ? AND username = ?' . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$schoolId, $username, $ignoreId] : [$schoolId, $username];
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function emailTaken(string $email, int $schoolId, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE school_id = ? AND email = ?' . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$schoolId, $email, $ignoreId] : [$schoolId, $email];
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function create(array $data, string $password): int
    {
        return static::insert([
            'school_id'            => $data['school_id'],
            'username'             => $data['username'],
            'full_name'            => $data['full_name'],
            'email'                => $data['email'] ?: null,
            'phone'                => $data['phone'] ?: null,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'user_type'            => $data['user_type'],
            'role_id'              => $data['role_id'],
            'staff_id'             => $data['staff_id'] ?? null,
            'guardian_id'          => $data['guardian_id'] ?? null,
            'student_id'           => $data['student_id'] ?? null,
            'must_change_password' => 1,
            'is_active'            => 1,
            'created_by'           => $data['created_by'],
        ]);
    }

    public static function recordFailure(int $userId, int $limit): void
    {
        static::execute(
            'UPDATE users
                SET failed_attempts = failed_attempts + 1,
                    locked_until = CASE WHEN failed_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE) ELSE locked_until END
              WHERE id = ?',
            [$limit, $userId]
        );
    }

    public static function recordSuccess(int $userId): void
    {
        static::execute(
            'UPDATE users
                SET failed_attempts = 0, locked_until = NULL,
                    last_login_at = NOW(), last_login_ip = ?
              WHERE id = ?',
            [client_ip(), $userId]
        );
    }

    public static function resetPassword(int $userId, int $actorId): string
    {
        $plain   = 'SSMS-' . strtoupper(bin2hex(random_bytes(3)));
        $history = static::db()->prepare(
            'INSERT INTO password_history (user_id, password_hash, changed_by, change_reason) VALUES (?, ?, ?, ?)'
        );
        $history->execute([$userId, password_hash($plain, PASSWORD_DEFAULT), $actorId, 'admin_reset']);

        static::execute(
            'UPDATE users
                SET password_hash = ?, must_change_password = 1,
                    failed_attempts = 0, locked_until = NULL
              WHERE id = ?',
            [password_hash($plain, PASSWORD_DEFAULT), $userId]
        );

        return $plain;
    }

    public static function setPassword(int $userId, string $password): void
    {
        static::execute(
            'UPDATE users SET password_hash = ?, must_change_password = 0, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $userId]
        );
    }

    public static function changeOwnPassword(int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        static::db()->prepare(
            'INSERT INTO password_history (user_id, password_hash, changed_by, change_reason) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $hash, $userId, 'self_reset']);
        static::execute(
            'UPDATE users SET password_hash = ?, must_change_password = 0, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [$hash, $userId]
        );
    }

    public static function findForPasswordReset(string $identity): ?array
    {
        return static::one(
            'SELECT id, username, email, full_name, school_id FROM users WHERE (username = ? OR (email IS NOT NULL AND email = ?)) AND is_active = 1 LIMIT 1',
            [$identity, $identity]
        );
    }

    public static function validPasswordReset(string $token): ?array
    {
        $hash = hash('sha256', $token);
        return static::one(
            'SELECT id, user_id FROM password_resets WHERE token_hash = ? AND is_used = 0 AND expires_at > NOW() LIMIT 1',
            [$hash]
        );
    }

    public static function applyPasswordReset(int $resetId, int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        static::db()->prepare(
            'INSERT INTO password_history (user_id, password_hash, changed_by, change_reason) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $hash, $userId, 'self_reset']);
        static::execute(
            'UPDATE users SET password_hash = ?, must_change_password = 0, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [$hash, $userId]
        );
        static::execute('UPDATE password_resets SET is_used = 1, used_at = NOW() WHERE id = ?', [$resetId]);
    }

    public static function updateProfile(int $id, array $data): void
    {
        static::updateRow($id, [
            'full_name' => $data['full_name'],
            'email' => ($data['email'] ?? '') ?: null,
            'phone' => ($data['phone'] ?? '') ?: null,
        ]);
    }
}
