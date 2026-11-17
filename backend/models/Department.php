<?php
declare(strict_types=1);

final class Department extends Model
{
    protected static string $table = 'departments';

    public static function forSchool(int $schoolId): array
    {
        return static::query(
            "SELECT d.*,
                    (SELECT CONCAT(s.first_name, ' ', s.last_name)
                       FROM staff s WHERE s.id = d.head_staff_id) AS head_name,
                    (SELECT COUNT(*) FROM staff s
                      WHERE s.department_id = d.id AND s.employment_status IN ('active','probation','contract')) AS staff_count
               FROM departments d
              WHERE d.school_id = ?
              ORDER BY d.type, d.name",
            [$schoolId]
        );
    }

    public static function activeForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM departments WHERE school_id = ? AND is_active = 1 ORDER BY type, name',
            [$schoolId]
        );
    }

    public static function nameTaken(int $schoolId, string $name, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM departments WHERE school_id = ? AND name = ?';
        $params = [$schoolId, $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }
}
