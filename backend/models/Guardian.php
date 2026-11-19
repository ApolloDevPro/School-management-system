<?php
declare(strict_types=1);

/**
 * Guardians and their links to students.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Guardian extends Model
{
    protected static string $table = 'guardians';

    public static function listForSchool(int $schoolId, string $search = ''): array
    {
        $sql = 'SELECT g.*,
                       (SELECT COUNT(*) FROM student_guardians sg WHERE sg.guardian_id = g.id) AS student_count
                  FROM guardians g
                 WHERE g.school_id = ?';
        $params = [$schoolId];
        if ($search !== '') {
            $sql .= ' AND (g.first_name LIKE ? OR g.last_name LIKE ? OR g.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY g.last_name, g.first_name LIMIT 400';
        return static::query($sql, $params);
    }

    public static function studentsOf(int $guardianId): array
    {
        return static::query(
            'SELECT s.id, s.first_name, s.last_name, s.admission_number, s.status,
                    sg.is_primary, sg.is_fee_payer, c.name AS class_name
               FROM student_guardians sg
               JOIN students s ON s.id = sg.student_id
               LEFT JOIN classes c ON c.id = s.current_class_id
              WHERE sg.guardian_id = ?
              ORDER BY s.last_name, s.first_name',
            [$guardianId]
        );
    }

    public static function link(int $studentId, int $guardianId, string $relationship, bool $isPrimary, bool $isFeePayer, bool $livesWith): void
    {
        static::execute(
            'INSERT INTO student_guardians (student_id, guardian_id, relationship, is_primary, lives_with, is_fee_payer)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE relationship = VALUES(relationship), is_primary = VALUES(is_primary),
                                     lives_with = VALUES(lives_with), is_fee_payer = VALUES(is_fee_payer)',
            [$studentId, $guardianId, $relationship, $isPrimary ? 1 : 0, $livesWith ? 1 : 0, $isFeePayer ? 1 : 0]
        );
    }

    public static function unlink(int $studentId, int $guardianId): void
    {
        static::execute(
            'DELETE FROM student_guardians WHERE student_id = ? AND guardian_id = ?',
            [$studentId, $guardianId]
        );
    }
}
