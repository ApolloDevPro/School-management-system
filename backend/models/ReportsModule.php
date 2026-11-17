<?php
declare(strict_types=1);

final class ReportsModule extends Model
{
    private static function safeQuery(string $sql, array $params = []): array
    {
        try {
            return static::query($sql, $params);
        } catch (Throwable) {
            return [];
        }
    }

    private static function safeOne(string $sql, array $params = []): ?array
    {
        try {
            return static::one($sql, $params);
        } catch (Throwable) {
            return null;
        }
    }

    // ---------------------------------------------------------------- KPIs

    public static function schoolDashboard(int $schoolId): ?array
    {
        return self::safeOne(
            'SELECT active_students, boarders, active_staff, active_classes,
                    outstanding_fees, fees_collected_this_month, absent_today, open_tickets
                   FROM vw_school_dashboard WHERE school_id=?',
            [$schoolId]
        );
    }

    // ------------------------------------------------------------ Students

    public static function studentPlacement(int $schoolId): array
    {
        return self::safeQuery(
            "SELECT student_id, admission_number, full_name, gender, class_name,
                    stream_name, house_name, status, student_type
               FROM vw_student_placement WHERE school_id=? AND status='active'
               ORDER BY class_name, full_name LIMIT 2000",
            [$schoolId]
        );
    }

    // -------------------------------------------------------------- Finance

    public static function debtors(int $schoolId, int $yearId): array
    {
        return self::safeQuery(
            "SELECT student_name, admission_number, class_name, total_charged,
                    total_paid, balance, overdue_amount, days_overdue,
                    primary_guardian, guardian_phone
               FROM vw_debtor_list
              WHERE school_id=? AND academic_year_id=? AND balance > 0
              ORDER BY balance DESC LIMIT 500",
            [$schoolId, $yearId]
        );
    }

    public static function feeCollection(int $schoolId, string $from, string $to): array
    {
        return self::safeQuery(
            "SELECT payment_date, channel, method_name, payment_count, amount_collected
               FROM vw_fee_collection_summary
              WHERE school_id=? AND payment_date BETWEEN ? AND ?
              ORDER BY payment_date DESC LIMIT 500",
            [$schoolId, $from, $to]
        );
    }

    // ---------------------------------------------------------- Attendance

    public static function attendanceSummary(int $schoolId, string $date): array
    {
        return self::safeQuery(
            'SELECT class_name, stream_name, marked_count, present_count,
                    absent_count, late_count, excused_count, attendance_percent
               FROM vw_attendance_daily_summary
              WHERE school_id=? AND attendance_date=?
              ORDER BY class_name',
            [$schoolId, $date]
        );
    }

    // -------------------------------------------------------- Examinations

    public static function examClassPerformance(int $schoolId, int $examId): array
    {
        return self::safeQuery(
            "SELECT cp.exam_name, cp.class_name, cp.subject_name,
                    cp.candidates, cp.average_percent, cp.highest_percent,
                    cp.lowest_percent, cp.passed_count, cp.pass_rate
               FROM vw_exam_class_performance cp
               JOIN exams e ON e.id = cp.exam_id
              WHERE e.school_id=? AND cp.exam_id=?
              ORDER BY cp.class_name, cp.subject_name",
            [$schoolId, $examId]
        );
    }

    public static function exams(int $schoolId): array
    {
        return static::query(
            "SELECT id, name, exam_type, status FROM exams WHERE school_id=? ORDER BY created_at DESC LIMIT 100",
            [$schoolId]
        );
    }

    // ------------------------------------------------------------------ HR

    public static function leaveBalances(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT staff_name, leave_type, days_per_year, days_taken, days_remaining, days_pending
               FROM vw_leave_balance WHERE school_id=? ORDER BY staff_name, leave_type LIMIT 500',
            [$schoolId]
        );
    }

    public static function payrollSummary(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT period_label, status, employees_paid, total_gross, total_paye,
                    total_nssf_employee, total_nssf_employer, total_net_pay
               FROM vw_payroll_summary WHERE school_id=? ORDER BY payroll_period_id DESC LIMIT 24',
            [$schoolId]
        );
    }

    public static function contractExpiry(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT staff_name, position, contract_type, start_date, end_date,
                    days_to_expiry, status
               FROM vw_contract_expiry_alerts WHERE school_id=? ORDER BY days_to_expiry LIMIT 100',
            [$schoolId]
        );
    }

    // --------------------------------------------- Library / Boarding / Transport / Inventory

    public static function libraryStock(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT title, author, category_name, total_copies, available_copies,
                    copies_out, lost_copies, status
               FROM vw_library_stock_status WHERE school_id=? ORDER BY title LIMIT 500',
            [$schoolId]
        );
    }

    public static function overdueBooks(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT title, accession_number, borrower_name, admission_number,
                    borrower_type, issue_date, due_date, days_overdue, status
               FROM vw_overdue_books WHERE school_id=? ORDER BY days_overdue DESC LIMIT 300',
            [$schoolId]
        );
    }

    public static function dormitoryOccupancy(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT dormitory_name, dormitory_type, capacity, occupied_beds,
                    vacancies, occupancy_percent, warden_name
               FROM vw_dormitory_occupancy WHERE school_id=? ORDER BY dormitory_name',
            [$schoolId]
        );
    }

    public static function routeLoad(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT route_code, route_name, plate_number, seating_capacity,
                    students_allocated, capacity_percent, status
               FROM vw_route_load WHERE school_id=? ORDER BY route_name',
            [$schoolId]
        );
    }

    public static function lowStock(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT item_code, item_name, category_name, unit, current_stock,
                    reorder_level, shortfall, status
               FROM vw_low_stock_items WHERE school_id=? ORDER BY shortfall DESC LIMIT 300',
            [$schoolId]
        );
    }

    // ------------------------------------------------------------ Compliance

    public static function licenceAlerts(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT licence_type, licence_number, issuing_body, issue_date,
                    expiry_date, days_to_expiry, status
               FROM vw_licence_expiry_alerts WHERE school_id=? ORDER BY days_to_expiry LIMIT 50',
            [$schoolId]
        );
    }

    // ------------------------------------------------------------- Audit

    public static function auditSummary(int $schoolId): array
    {
        return self::safeQuery(
            'SELECT user_name, module, action, activity_date, action_count
               FROM vw_audit_summary WHERE school_id=? ORDER BY activity_date DESC, action_count DESC LIMIT 300',
            [$schoolId]
        );
    }

    // -------------------------------------------------------- Shared lookups

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, is_active FROM academic_years WHERE school_id=? ORDER BY is_active DESC, start_date DESC',
            [$schoolId]
        );
    }

    public static function activeYear(int $schoolId): ?array
    {
        return static::one(
            'SELECT id, name FROM academic_years WHERE school_id=? AND is_active=1 LIMIT 1',
            [$schoolId]
        );
    }

    // ================================================================
    // Chart data methods
    // ================================================================

    public static function feeCollectionChart(int $schoolId, string $from, string $to): array
    {
        $rows = self::safeQuery(
            'SELECT payment_date, SUM(amount_collected) AS total
               FROM vw_fee_collection_summary
              WHERE school_id=? AND payment_date BETWEEN ? AND ?
              GROUP BY payment_date
              ORDER BY payment_date ASC',
            [$schoolId, $from, $to]
        );
        return [
            'labels'   => array_column($rows, 'payment_date'),
            'datasets' => [[
                'label' => 'Fees collected',
                'data'  => array_column($rows, 'total'),
                'borderColor' => '#2563eb',
                'backgroundColor' => 'rgba(37, 99, 235, 0.1)',
                'tension' => 0.3,
                'fill' => true,
            ]],
        ];
    }

    public static function studentsByClassChart(int $schoolId): array
    {
        $rows = self::safeQuery(
            "SELECT class_name AS label, COUNT(*) AS value
               FROM vw_student_placement
              WHERE school_id=? AND status='active'
              GROUP BY class_name
              ORDER BY value DESC LIMIT 12",
            [$schoolId]
        );
        return [
            'labels'   => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Students per class',
                'data'  => array_column($rows, 'value'),
                'backgroundColor' => [
                    '#2563eb', '#16a34a', '#d97706', '#dc2626', '#0891b2',
                    '#7c3aed', '#be185d', '#b45309', '#0d9488', '#6366f1',
                    '#84cc16', '#f59e0b',
                ],
            ]],
        ];
    }

    public static function studentsByStatusChart(int $schoolId): array
    {
        $rows = self::safeQuery(
            "SELECT status AS label, COUNT(*) AS value
               FROM vw_student_placement
              WHERE school_id=?
              GROUP BY status",
            [$schoolId]
        );
        $map = ['active' => 'Active', 'suspended' => 'Suspended', 'withdrawn' => 'Withdrawn', 'transferred_out' => 'Transferred', 'completed' => 'Completed', 'graduated' => 'Graduated'];
        $labels = []; $values = [];
        foreach ($rows as $r) {
            $labels[] = $map[$r['label']] ?? $r['label'];
            $values[] = (int)$r['value'];
        }
        return [
            'labels'   => $labels,
            'datasets' => [[
                'label' => 'Students by status',
                'data'  => $values,
                'backgroundColor' => ['#22c55e', '#eab308', '#ef4444', '#64748b', '#10b981', '#6366f1'],
            ]],
        ];
    }

    public static function attendanceChart(int $schoolId, string $from, string $to): array
    {
        $rows = self::safeQuery(
            'SELECT attendance_date AS label,
                    ROUND(100.0 * SUM(status = \'present\') / NULLIF(COUNT(*), 0), 2) AS avg_rate
               FROM student_attendance
              WHERE school_id=? AND attendance_date BETWEEN ? AND ? AND record_scope=\'daily\'
              GROUP BY attendance_date
              ORDER BY attendance_date ASC',
            [$schoolId, $from, $to]
        );
        return [
            'labels'   => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Daily attendance %',
                'data'  => array_column($rows, 'avg_rate'),
                'borderColor' => '#16a34a',
                'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                'tension' => 0.3,
                'fill' => true,
            ]],
        ];
    }

    public static function examPerformanceChart(int $schoolId, int $examId): array
    {
        $rows = self::safeQuery(
            "SELECT cp.class_name AS label, cp.average_percent AS value
               FROM vw_exam_class_performance cp
               JOIN exams e ON e.id = cp.exam_id
              WHERE e.school_id=? AND cp.exam_id=?
              ORDER BY cp.class_name LIMIT 20",
            [$schoolId, $examId]
        );
        return [
            'labels'   => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Average % per class',
                'data'  => array_column($rows, 'value'),
                'backgroundColor' => '#3b82f6',
            ]],
        ];
    }

    public static function dormOccupancyChart(int $schoolId): array
    {
        $rows = self::safeQuery(
            'SELECT dormitory_name AS label, occupancy_percent AS value
               FROM vw_dormitory_occupancy
              WHERE school_id=?
              ORDER BY value DESC LIMIT 12',
            [$schoolId]
        );
        return [
            'labels'   => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Occupancy %',
                'data'  => array_column($rows, 'value'),
                'backgroundColor' => '#0891b2',
            ]],
        ];
    }

    public static function routeLoadChart(int $schoolId): array
    {
        $rows = self::safeQuery(
            'SELECT route_name AS label, capacity_percent AS value
               FROM vw_route_load
              WHERE school_id=?
              ORDER BY value DESC LIMIT 12',
            [$schoolId]
        );
        return [
            'labels'   => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Route capacity %',
                'data'  => array_column($rows, 'value'),
                'backgroundColor' => '#b45309',
            ]],
        ];
    }

    public static function lowStockChart(int $schoolId): array
    {
        $rows = self::safeQuery(
            'SELECT item_name AS label, current_stock AS value
               FROM vw_low_stock_items
              WHERE school_id=?
              ORDER BY shortfall DESC LIMIT 12',
            [$schoolId]
        );
        return [
            'labels'   => array_column($rows, 'label'),
            'datasets' => [[
                'label' => 'Current stock',
                'data'  => array_column($rows, 'value'),
                'backgroundColor' => '#dc2626',
            ]],
        ];
    }
}
