<?php
declare(strict_types=1);

/**
 * Staff, contracts, leave, payroll and recruitment.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class HrModule extends Model
{
    private const TABLES = ['staff', 'leave_types', 'leave_requests', 'payroll_periods', 'payroll_items'];

    public static function insertInto(string $table, array $data): int
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new InvalidArgumentException('Unsupported HR table.');
        }
        $columns = array_keys($data);
        $statement = static::db()->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $statement->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    public static function updateIn(string $table, int $id, array $data): bool
    {
        if (!in_array($table, self::TABLES, true) || !$data) {
            throw new InvalidArgumentException('Unsupported HR update.');
        }
        $assignments = implode(', ', array_map(
            static fn (string $column): string => $column . ' = ?',
            array_keys($data)
        ));
        $statement = static::db()->prepare('UPDATE ' . $table . ' SET ' . $assignments . ' WHERE id = ?');
        $statement->execute([...array_values($data), $id]);
        return $statement->rowCount() > 0;
    }

    public static function staffForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT s.id, s.staff_number, s.first_name, s.middle_name, s.last_name,
                    s.phone, s.email, s.position, s.employment_type, s.employment_status,
                    s.date_of_appointment, d.name AS department_name
               FROM staff s
               LEFT JOIN departments d ON d.id = s.department_id
              WHERE s.school_id = ?
              ORDER BY s.last_name, s.first_name',
            [$schoolId]
        );
    }

    public static function staff(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM staff WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function staffNumberTaken(int $schoolId, string $number, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM staff WHERE school_id = ? AND staff_number = ?';
        $params = [$schoolId, $number];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function leaveTypes(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM leave_types WHERE school_id = ? ORDER BY name',
            [$schoolId]
        );
    }

    public static function leaveType(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM leave_types WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function leaveRequests(int $schoolId): array
    {
        return static::query(
            'SELECT lr.*, lt.name AS leave_type, lt.is_paid,
                    s.staff_number, s.first_name, s.last_name
               FROM leave_requests lr
               JOIN staff s ON s.id = lr.staff_id
               JOIN leave_types lt ON lt.id = lr.leave_type_id
              WHERE s.school_id = ?
              ORDER BY FIELD(lr.status, \'pending\', \'approved\', \'rejected\', \'cancelled\'),
                       lr.start_date DESC, lr.id DESC',
            [$schoolId]
        );
    }

    public static function leaveRequest(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT lr.* FROM leave_requests lr
               JOIN staff s ON s.id = lr.staff_id
              WHERE lr.id = ? AND s.school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function payrollPeriods(int $schoolId): array
    {
        return static::query(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM payroll_items i WHERE i.payroll_period_id = p.id) AS item_count
               FROM payroll_periods p
              WHERE p.school_id = ?
              ORDER BY p.period_year DESC, p.period_month DESC',
            [$schoolId]
        );
    }

    public static function payrollPeriod(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM payroll_periods WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function payrollItems(int $periodId, int $schoolId): array
    {
        return static::query(
            'SELECT i.*,
                    s.staff_number, s.first_name, s.last_name
               FROM payroll_items i
               JOIN payroll_periods p ON p.id = i.payroll_period_id
               JOIN staff s ON s.id = i.staff_id
              WHERE p.id = ? AND p.school_id = ?
              ORDER BY s.last_name, s.first_name',
            [$periodId, $schoolId]
        );
    }
}
