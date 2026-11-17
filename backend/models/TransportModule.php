<?php
declare(strict_types=1);

final class TransportModule extends Model
{
    public static function routes(int $schoolId): array
    {
        return static::query(
            'SELECT id,route_code,route_name,distance_km,fare_per_term,status FROM routes WHERE school_id=? ORDER BY route_name LIMIT 300',
            [$schoolId]
        );
    }

    public static function createRoute(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO routes (school_id,route_code,route_name,description,distance_km,fare_per_term,status)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$data['school_id'],$data['code'],$data['name'],$data['description'],$data['distance'],$data['fare'],'active']);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE routes SET status=? WHERE id=? AND school_id=?',[$status,$id,$schoolId]) > 0;
    }
}
