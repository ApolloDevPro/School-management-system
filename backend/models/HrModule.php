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

    public static function payrollItem(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT i.*, s.staff_number, s.first_name, s.middle_name, s.last_name,
                    s.position, s.bank_name, s.bank_account_number, s.nssf_number, s.ura_tin,
                    p.period_label, p.period_year, p.period_month, p.start_date, p.end_date, p.status
               FROM payroll_items i
               JOIN payroll_periods p ON p.id = i.payroll_period_id
               JOIN staff s ON s.id = i.staff_id
              WHERE i.id = ? AND p.school_id = ?',
            [$id, $schoolId]
        );
    }

    /**
     * Uganda PAYE for one month, on taxable pay after the employee NSSF
     * contribution. Bands run in Uganda shillings.
     */
    public static function payeFor(float $taxable): float
    {
        $taxable = max(0.0, $taxable);
        if ($taxable <= 235000) {
            return 0.0;
        }
        if ($taxable <= 335000) {
            return round(0.10 * ($taxable - 235000), 2);
        }
        if ($taxable <= 410000) {
            return round(10000 + 0.20 * ($taxable - 335000), 2);
        }
        if ($taxable <= 10000000) {
            return round(25000 + 0.30 * ($taxable - 410000), 2);
        }
        return round(2902000 + 0.40 * ($taxable - 10000000), 2);
    }

    /**
     * NSSF and PAYE for a monthly gross. The employee contributes 5% and the
     * employer 10%; only the employee share reduces taxable pay.
     */
    public static function statutory(float $gross): array
    {
        $gross = max(0.0, $gross);
        $employee = round($gross * 0.05, 2);
        $employer = round($gross * 0.10, 2);
        $taxable = max(0.0, $gross - $employee);
        return [
            'nssf_employee' => $employee,
            'nssf_employer' => $employer,
            'taxable_pay' => round($taxable, 2),
            'paye' => self::payeFor($taxable),
        ];
    }

    /**
     * Recalculate PAYE and NSSF for every item in an open period, keeping the
     * basic pay, allowances and other deductions that were already entered.
     */
    public static function recalculatePeriod(int $periodId, int $schoolId): int
    {
        $items = static::query(
            'SELECT i.id, i.gross_pay, i.other_deductions
               FROM payroll_items i
               JOIN payroll_periods p ON p.id = i.payroll_period_id
              WHERE p.id = ? AND p.school_id = ?',
            [$periodId, $schoolId]
        );
        $updated = 0;
        foreach ($items as $item) {
            $gross = (float)$item['gross_pay'];
            $other = (float)$item['other_deductions'];
            $tax = static::statutory($gross);
            $total = round($tax['nssf_employee'] + $tax['paye'] + $other, 2);
            if ($total > $gross) {
                $other = max(0.0, round($gross - $tax['nssf_employee'] - $tax['paye'], 2));
                $total = round($tax['nssf_employee'] + $tax['paye'] + $other, 2);
            }
            static::execute(
                'UPDATE payroll_items SET nssf_employee = ?, nssf_employer = ?, paye = ?,
                        other_deductions = ?, total_deductions = ?, net_pay = ?
                  WHERE id = ?',
                [
                    $tax['nssf_employee'], $tax['nssf_employer'], $tax['paye'],
                    $other, $total, round($gross - $total, 2), (int)$item['id'],
                ]
            );
            $updated++;
        }
        return $updated;
    }
}
