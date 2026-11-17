<?php
declare(strict_types=1);

final class SchoolClass extends Model
{
    protected static string $table = 'classes';

    public static function forSchool(int $schoolId): array
    {
        return static::query(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM streams s WHERE s.class_id = c.id AND s.is_active = 1) AS stream_count,
                    (SELECT COUNT(*) FROM students st
                      WHERE st.current_class_id = c.id AND st.status = 'active') AS student_count
               FROM classes c
              WHERE c.school_id = ?
              ORDER BY c.order_index, c.name",
            [$schoolId]
        );
    }

    public static function activeForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY order_index, name',
            [$schoolId]
        );
    }

    public static function nameTaken(int $schoolId, string $name, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM classes WHERE school_id = ? AND name = ?';
        $params = [$schoolId, $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }
}
