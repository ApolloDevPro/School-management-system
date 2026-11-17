<?php
declare(strict_types=1);

final class InventoryModule extends Model
{
    public static function items(int $schoolId): array
    {
        return static::query(
            'SELECT id,item_code,name,unit,current_stock,reorder_level,status FROM items WHERE school_id=? ORDER BY name LIMIT 500',
            [$schoolId]
        );
    }

    public static function requests(int $schoolId): array
    {
        return static::query(
            'SELECT id,request_number,request_date,needed_by,estimated_total,status,justification FROM purchase_requests WHERE school_id=? ORDER BY request_date DESC,id DESC LIMIT 300',
            [$schoolId]
        );
    }

    public static function createRequest(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO purchase_requests (request_number,school_id,requested_by,request_date,needed_by,priority,justification,estimated_total,status)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([$data['number'],$data['school_id'],$data['user_id'],date('Y-m-d'),$data['needed_by'],$data['priority'],$data['justification'],$data['amount'],'draft']);
        return (int)static::db()->lastInsertId();
    }

    public static function transition(int $id, int $schoolId, string $from, string $to, ?int $userId = null): bool
    {
        $approved = $to === 'approved' ? ', approved_by=?, approved_at=NOW()' : '';
        $params = $userId === null ? [$to,$id,$schoolId,$from] : [$to,$userId,$id,$schoolId,$from];
        return static::execute(
            "UPDATE purchase_requests SET status=?{$approved} WHERE id=? AND school_id=? AND status=?",
            $params
        ) > 0;
    }

    public static function ownsDraft(int $id, int $schoolId, int $userId): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM purchase_requests WHERE id=? AND school_id=? AND requested_by=? AND status=?',[$id,$schoolId,$userId,'draft']);
    }
}
