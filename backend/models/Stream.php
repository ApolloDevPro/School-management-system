<?php
declare(strict_types=1);

final class Stream extends Model
{
    protected static string $table = 'streams';

    public static function forClass(int $classId): array
    {
        return static::query(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM students st
                      WHERE st.current_stream_id = s.id AND st.status = \'active\') AS student_count,
                    (SELECT CONCAT(u.first_name, \' \', u.last_name)
                       FROM staff u WHERE u.id = s.class_teacher_id) AS class_teacher_name
               FROM streams s
              WHERE s.class_id = ?
              ORDER BY s.name',
            [$classId]
        );
    }

    public static function forSchool(int $schoolId): array
    {
        return static::query(
            'SELECT s.*, c.name AS class_name, c.level
               FROM streams s
               JOIN classes c ON c.id = s.class_id
              WHERE c.school_id = ?
              ORDER BY c.order_index, c.name, s.name',
            [$schoolId]
        );
    }

    public static function activeForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT s.*, c.name AS class_name, c.level
               FROM streams s
               JOIN classes c ON c.id = s.class_id
              WHERE c.school_id = ? AND s.is_active = 1 AND c.is_active = 1
              ORDER BY c.order_index, c.name, s.name',
            [$schoolId]
        );
    }

    public static function nameTaken(int $classId, string $name, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM streams WHERE class_id = ? AND name = ?';
        $params = [$classId, $name];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }
}
