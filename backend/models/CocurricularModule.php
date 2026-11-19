<?php
declare(strict_types=1);

/**
 * Clubs and memberships.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class CocurricularModule extends Model
{
    public static function clubs(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,club_category,meeting_day,meeting_venue,status FROM clubs WHERE school_id=? ORDER BY name LIMIT 300',
            [$schoolId]
        );
    }

    public static function createClub(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO clubs (school_id,name,club_category,description,status) VALUES (?,?,?,?,?)'
        )->execute([$data['school_id'],$data['name'],$data['category'],$data['description'],'active']);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE clubs SET status=? WHERE id=? AND school_id=?',[$status,$id,$schoolId]) > 0;
    }
}
