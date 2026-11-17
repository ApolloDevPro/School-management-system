<?php
declare(strict_types=1);

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
}
