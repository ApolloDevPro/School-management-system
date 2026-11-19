<?php
declare(strict_types=1);

/**
 * Student transfers.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Transfer extends Model
{
    protected static string $table = 'transfers';

    public static function listForSchool(int $schoolId, string $direction = ''): array
    {
        $sql = 'SELECT t.*, s.first_name, s.last_name, s.admission_number,
                       c.name AS receiving_class_name
                  FROM transfers t
                  JOIN students s ON s.id = t.student_id
                  LEFT JOIN classes c ON c.id = t.receiving_class_id
                 WHERE s.school_id = ?';
        $params = [$schoolId];
        if ($direction !== '') {
            $sql .= ' AND t.direction = ?';
            $params[] = $direction;
        }
        $sql .= ' ORDER BY t.transfer_date DESC, t.id DESC LIMIT 400';
        return static::query($sql, $params);
    }
}
