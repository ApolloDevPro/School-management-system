<?php
declare(strict_types=1);

final class House extends Model
{
    protected static string $table = 'houses';

    public static function forSchool(int $schoolId): array
    {
        return static::query(
            "SELECT h.*,
                    (SELECT CONCAT(s.first_name, ' ', s.last_name)
                       FROM staff s WHERE s.id = h.house_master_staff_id) AS house_master_name,
                    (SELECT COUNT(*) FROM students st
                      WHERE st.house_id = h.id AND st.status = 'active') AS student_count
               FROM houses h
              WHERE h.school_id = ?
              ORDER BY h.name",
            [$schoolId]
        );
    }

    public static function activeForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM houses WHERE school_id = ? AND is_active = 1 ORDER BY name',
            [$schoolId]
        );
    }

    public static function nameTaken(int $schoolId, string $name, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM houses WHERE school_id = ? AND name = ?';
        $params = [$schoolId, $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }
}
