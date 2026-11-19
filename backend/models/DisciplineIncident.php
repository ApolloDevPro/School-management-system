<?php
declare(strict_types=1);

/**
 * Discipline incidents.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class DisciplineIncident extends Model
{
    protected static string $table = 'discipline_incidents';

    public static function listForSchool(int $schoolId, string $status = ''): array
    {
        $sql = 'SELECT d.*, s.first_name, s.last_name, s.admission_number,
                       c.name AS class_name
                  FROM discipline_incidents d
                  JOIN students s ON s.id = d.student_id
                  LEFT JOIN classes c ON c.id = s.current_class_id
                 WHERE s.school_id = ?';
        $params = [$schoolId];
        if ($status !== '') {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY d.incident_date DESC, d.id DESC LIMIT 400';
        return static::query($sql, $params);
    }

    public static function forStudent(int $studentId): array
    {
        return static::query(
            'SELECT * FROM discipline_incidents WHERE student_id = ? ORDER BY incident_date DESC, id DESC',
            [$studentId]
        );
    }
}
