<?php
declare(strict_types=1);

/**
 * Student withdrawals.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Withdrawal extends Model
{
    protected static string $table = 'withdrawals';

    public static function listForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT w.*, s.first_name, s.last_name, s.admission_number, c.name AS class_name
               FROM withdrawals w
               JOIN students s ON s.id = w.student_id
               LEFT JOIN classes c ON c.id = s.current_class_id
              WHERE s.school_id = ?
              ORDER BY w.withdrawal_date DESC, w.id DESC LIMIT 400',
            [$schoolId]
        );
    }
}
