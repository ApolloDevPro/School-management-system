<?php
declare(strict_types=1);

/**
 * Admission applications and their decisions.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class AdmissionApplication extends Model
{
    protected static string $table = 'admission_applications';

    public static function listForSchool(int $schoolId, string $status = ''): array
    {
        $sql = 'SELECT a.*, c.name AS applied_class_name, y.name AS year_name,
                       st.first_name AS student_first_name
                  FROM admission_applications a
                  LEFT JOIN classes c ON c.id = a.applied_class_id
                  JOIN academic_years y ON y.id = a.academic_year_id
                  LEFT JOIN students st ON st.id = a.student_id
                 WHERE a.school_id = ?';
        $params = [$schoolId];
        if ($status !== '') {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY a.id DESC LIMIT 400';
        return static::query($sql, $params);
    }
}
