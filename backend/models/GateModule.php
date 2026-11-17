<?php
declare(strict_types=1);

final class GateModule extends Model
{
    public static function visitors(int $schoolId): array
    {
        return static::query(
            'SELECT id,full_name,organisation,purpose,host_name,time_in,time_out,status FROM visitors WHERE school_id=? ORDER BY time_in DESC LIMIT 300',
            [$schoolId]
        );
    }

    public static function createVisitor(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO visitors (school_id,full_name,phone,organisation,host_type,host_name,purpose,vehicle_plate,badge_number,checked_in_by,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([$data['school_id'],$data['name'],$data['phone'],$data['organisation'],'other',$data['host'],$data['purpose'],$data['plate'],$data['badge'],$data['user_id'],'inside']);
        return (int)static::db()->lastInsertId();
    }

    public static function checkOut(int $id, int $schoolId, int $userId): bool
    {
        return static::execute("UPDATE visitors SET status='out',time_out=NOW(),checked_out_by=? WHERE id=? AND school_id=? AND status='inside'",[$userId,$id,$schoolId]) > 0;
    }
}
