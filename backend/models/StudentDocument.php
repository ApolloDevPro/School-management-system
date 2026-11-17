<?php
declare(strict_types=1);

final class StudentDocument extends Model
{
    protected static string $table = 'student_documents';

    public static function listForSchool(int $schoolId, string $type = ''): array
    {
        $sql = 'SELECT d.*, s.first_name, s.last_name, s.admission_number
                  FROM student_documents d
                  JOIN students s ON s.id = d.student_id
                 WHERE s.school_id = ?';
        $params = [$schoolId];
        if ($type !== '') {
            $sql .= ' AND d.doc_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY d.id DESC LIMIT 400';
        return static::query($sql, $params);
    }

    public static function forStudent(int $studentId): array
    {
        return static::query(
            'SELECT * FROM student_documents WHERE student_id = ? ORDER BY doc_type, id DESC',
            [$studentId]
        );
    }
}
