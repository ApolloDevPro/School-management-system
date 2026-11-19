<?php
declare(strict_types=1);

/**
 * Support tickets.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class SecurityModule extends Model
{
    public static function tickets(int $schoolId, int $userId, bool $all): array
    {
        $sql = 'SELECT t.id,t.ticket_number,t.category,t.subject,t.priority,t.status,t.created_at,u.full_name AS requester
                  FROM support_tickets t JOIN users u ON u.id=t.user_id WHERE t.school_id=?';
        $params = [$schoolId];
        if (!$all) {
            $sql .= ' AND t.user_id=?';
            $params[] = $userId;
        }
        return static::query($sql . ' ORDER BY t.created_at DESC LIMIT 300',$params);
    }

    public static function createTicket(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO support_tickets (ticket_number,school_id,user_id,category,subject,description,priority,status)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$data['number'],$data['school_id'],$data['user_id'],$data['category'],$data['subject'],$data['description'],$data['priority'],'open']);
        return (int)static::db()->lastInsertId();
    }

    public static function transition(int $id, int $schoolId, int $userId, bool $all, string $status): bool
    {
        $sql = 'UPDATE support_tickets SET status=?';
        if ($status === 'resolved') {
            $sql .= ',resolved_at=NOW()';
        }
        $sql .= ' WHERE id=? AND school_id=?';
        $params = [$status,$id,$schoolId];
        if (!$all) {
            $sql .= ' AND user_id=?';
            $params[] = $userId;
        }
        return static::execute($sql,$params) > 0;
    }

    public static function owns(int $id, int $schoolId, int $userId): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM support_tickets WHERE id=? AND school_id=? AND user_id=?',[$id,$schoolId,$userId]);
    }

    /**
     * Active sign-ins for the school. Overdue rows are closed first so the
     * list only shows sessions that can still be used.
     */
    public static function sessions(int $schoolId): array
    {
        static::execute(
            "UPDATE portal_sessions s
               JOIN users u ON u.id = s.user_id
                SET s.is_active = 0, s.logout_at = NOW(), s.logout_reason = 'timeout'
              WHERE u.school_id = ? AND s.is_active = 1 AND s.expires_at <= NOW()",
            [$schoolId]
        );

        // Keep the table small: ended rows older than 30 days are no longer useful.
        static::execute(
            'DELETE FROM portal_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );

        return static::query(
            'SELECT s.id, s.session_token_hash, s.ip_address, s.user_agent, s.device_type,
                    s.portal_type, s.started_at, s.last_activity_at, s.expires_at,
                    u.id AS user_id, u.full_name, u.username, u.user_type,
                    r.name AS role_name
               FROM portal_sessions s
               JOIN users u ON u.id = s.user_id
          LEFT JOIN roles r ON r.id = u.role_id
              WHERE u.school_id = ? AND s.is_active = 1
           ORDER BY s.last_activity_at DESC, s.id DESC
              LIMIT 500',
            [$schoolId]
        );
    }

    /**
     * One active session scoped to the school, for the revoke action.
     */
    public static function session(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT s.id, s.session_token_hash, s.user_id, u.full_name
               FROM portal_sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.id = ? AND u.school_id = ? AND s.is_active = 1',
            [$id, $schoolId]
        );
    }

    /**
     * End a session; the owner is signed out on their next request.
     */
    public static function revoke(int $id, int $schoolId): bool
    {
        return static::execute(
            "UPDATE portal_sessions s
               JOIN users u ON u.id = s.user_id
                SET s.is_active = 0, s.logout_at = NOW(), s.logout_reason = 'admin'
              WHERE s.id = ? AND u.school_id = ? AND s.is_active = 1",
            [$id, $schoolId]
        ) > 0;
    }

    /**
     * End every other active session belonging to one user on this school,
     * keeping the browser that pressed the button signed in.
     */
    public static function revokeOthers(int $userId, int $schoolId, string $keepTokenHash): int
    {
        return static::execute(
            "UPDATE portal_sessions s
               JOIN users u ON u.id = s.user_id
                SET s.is_active = 0, s.logout_at = NOW(), s.logout_reason = 'admin'
              WHERE s.user_id = ? AND u.school_id = ? AND s.is_active = 1
                AND s.session_token_hash <> ?",
            [$userId, $schoolId, $keepTokenHash]
        );
    }
}
