<?php
declare(strict_types=1);

final class Alumni extends Model
{
    protected static string $table = 'alumni';

    public static function listForSchool(int $schoolId, string $search = ''): array
    {
        $sql = 'SELECT a.*, s.first_name, s.last_name, s.admission_number
                  FROM alumni a
                  JOIN students s ON s.id = a.student_id
                 WHERE s.school_id = ?';
        $params = [$schoolId];
        if ($search !== '') {
            $sql .= ' AND (s.first_name LIKE ? OR s.last_name LIKE ? OR a.current_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY a.completion_year DESC, s.last_name LIMIT 400';
        return static::query($sql, $params);
    }

    public static function hasRecord(int $studentId): bool
    {
        return (int)static::scalar('SELECT COUNT(*) FROM alumni WHERE student_id = ?', [$studentId]) > 0;
    }
}
