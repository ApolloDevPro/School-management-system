<?php
declare(strict_types=1);

/**
 * Role-aware dashboard with school-wide statistics.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $roleSlug = strtolower(trim((string)($user['role_slug'] ?? '')));
        $userType = strtolower(trim((string)($user['user_type'] ?? 'staff')));

        // Parents and students use the shared portal dashboard.
        if (in_array($userType, ['parent', 'student'], true) || in_array($roleSlug, ['parent', 'student'], true)) {
            redirect('portal');
            return;
        }

        // One dashboard per role. Roles that do the same work share a view.
        $dashboards = [
            'administrator'       => 'leadership',
            'head_teacher'        => 'leadership',
            'deputy_head_teacher' => 'leadership',
            'director_of_studies' => 'director',
            'teacher'             => 'teacher',
            'bursar'              => 'bursar',
            'accountant'          => 'bursar',
            'registrar'           => 'registrar',
            'librarian'           => 'librarian',
            'nurse'               => 'nurse',
            'warden'              => 'warden',
            'gate_officer'        => 'gate',
            'hr_officer'          => 'hr',
            'storekeeper'         => 'storekeeper',
        ];

        $dashboard = $dashboards[$roleSlug] ?? null;
        if ($dashboard === null) {
            // Never fall back to the leadership dashboard: an unassigned or custom
            // role must not inherit school-wide figures.
            $this->unassigned();
            return;
        }

        $this->{$dashboard}();
    }

    private function scalar(string $sql, array $params = []): string|int|float|false
    {
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function all(string $sql, array $params = []): array
    {
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function safeScalar(string $sql, array $params = [], string|int|float $default = 0): string|int|float
    {
        try {
            $value = $this->scalar($sql, $params);
            return $value === false || $value === null ? $default : $value;
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function safeAll(string $sql, array $params = []): array
    {
        try {
            return $this->all($sql, $params);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function leadership(): void
    {
        $schoolId = school_id();

        $stats = [
            'students'    => (int)$this->safeScalar("SELECT COUNT(*) FROM students WHERE status <> 'withdrawn' AND school_id = ?", [$schoolId]),
            'staff'       => (int)$this->safeScalar("SELECT COUNT(*) FROM staff WHERE school_id = ? AND employment_status IN ('active','probation','contract')", [$schoolId]),
            'users'       => (int)$this->safeScalar('SELECT COUNT(*) FROM users WHERE school_id = ? AND is_active = 1', [$schoolId]),
            'fees'        => (float)$this->safeScalar("SELECT IFNULL(SUM(balance), 0) FROM fee_invoices WHERE school_id = ? AND status <> 'void'", [$schoolId]),
            'invoices'    => (int)$this->safeScalar("SELECT COUNT(*) FROM fee_invoices WHERE school_id = ? AND status <> 'void'", [$schoolId]),
            'paid'        => (float)$this->safeScalar("SELECT IFNULL(SUM(paid_amount), 0) FROM fee_invoices WHERE school_id = ? AND status <> 'void'", [$schoolId]),
            'applicants'  => (int)$this->safeScalar("SELECT COUNT(*) FROM admission_applications WHERE school_id = ? AND status IN ('pending','submitted','under_review')", [$schoolId]),
            'attendance'  => (int)$this->safeScalar("SELECT COUNT(*) FROM attendance_records ar JOIN attendance_registers r ON r.id = ar.register_id WHERE r.school_id = ? AND r.attendance_date = CURDATE() AND ar.status = 'present'", [$schoolId]),
            'overdue'     => (int)$this->safeScalar("SELECT COUNT(*) FROM borrowings b JOIN books bk ON bk.id=b.book_id WHERE b.school_id = ? AND b.status IN ('borrowed','overdue') AND b.due_date < CURDATE()", [$schoolId]),
            'low_stock'   => (int)$this->safeScalar("SELECT COUNT(*) FROM items WHERE school_id = ? AND status = 'active' AND current_stock <= reorder_level", [$schoolId]),
            'leave'       => (int)$this->safeScalar("SELECT COUNT(*) FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id WHERE s.school_id = ? AND lr.status = 'pending'", [$schoolId]),
            'tickets'     => (int)$this->safeScalar("SELECT COUNT(*) FROM support_tickets WHERE school_id = ? AND status IN ('open','in_progress','pending')", [$schoolId]),
            'locked'      => (int)$this->safeScalar("SELECT COUNT(*) FROM users WHERE school_id = ? AND (locked_until IS NOT NULL AND locked_until > NOW())", [$schoolId]),
            'failed_logins' => (int)$this->safeScalar("SELECT COUNT(*) FROM login_attempts WHERE school_id = ? AND was_successful = 0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)", [$schoolId]),
        ];

        $byClass = $this->safeAll(
            "SELECT c.name AS label, COUNT(s.id) AS total
               FROM classes c
               LEFT JOIN students s ON s.current_class_id = c.id AND s.status <> 'withdrawn' AND s.school_id = ?
              WHERE c.school_id = ? AND c.is_active = 1
              GROUP BY c.id, c.name, c.order_index
              ORDER BY c.order_index",
            [$schoolId, $schoolId]
        );

        $activeYear = (int)$this->safeScalar("SELECT id FROM academic_years WHERE school_id = ? AND is_active = 1 LIMIT 1", [$schoolId]);
        $activeTerm = (int)$this->safeScalar("SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE y.school_id = ? AND y.is_active = 1 AND t.is_active = 1 LIMIT 1", [$schoolId]);

        $lastBackup = $this->safeAll(
            "SELECT backup_name, status, created_at FROM data_backups WHERE school_id = ? ORDER BY created_at DESC LIMIT 1",
            [$schoolId]
        );
        $viewsOk = true;
        try {
            $this->scalar("SELECT 1 FROM vw_fee_collection_by_term LIMIT 1");
        } catch (Throwable $e) {
            $viewsOk = false;
        }

        $this->render('dashboard/admin', [
            'pageTitle' => 'Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'labels'    => array_column($byClass, 'label'),
            'values'    => array_map('intval', array_column($byClass, 'total')),
            'recent'    => AuditLog::recent(8),
            'activeYear' => $activeYear,
            'activeTerm' => $activeTerm,
            'lastBackup' => $lastBackup[0] ?? null,
            'viewsOk'    => $viewsOk,
        ]);
    }

    private function director(): void
    {
        $schoolId = school_id();

        $stats = [
            'classes'   => (int)$this->scalar("SELECT COUNT(*) FROM classes WHERE school_id = ? AND is_active = 1", [$schoolId]),
            'subjects'  => (int)$this->scalar("SELECT COUNT(*) FROM subjects WHERE school_id = ? AND is_active = 1", [$schoolId]),
            'students'  => (int)$this->scalar("SELECT COUNT(*) FROM students WHERE school_id = ? AND status <> 'withdrawn'", [$schoolId]),
            'staff'     => (int)$this->scalar("SELECT COUNT(*) FROM staff WHERE school_id = ? AND employment_status IN ('active','probation','contract')", [$schoolId]),
        ];

        $byClass = $this->all(
            "SELECT c.name AS label, COUNT(s.id) AS total
               FROM classes c
               LEFT JOIN students s ON s.current_class_id = c.id AND s.status <> 'withdrawn' AND s.school_id = ?
              WHERE c.school_id = ? AND c.is_active = 1
              GROUP BY c.id, c.name, c.order_index
              ORDER BY c.order_index",
            [$schoolId, $schoolId]
        );

        $unallocated = $this->all(
            "SELECT s.name AS subject_name, c.name AS class_name
               FROM subjects s
               JOIN classes c ON c.school_id = s.school_id
               LEFT JOIN subject_allocations sa ON sa.subject_id = s.id AND sa.class_id = c.id AND sa.academic_year_id = (
                    SELECT id FROM academic_years WHERE school_id = ? AND is_active = 1 LIMIT 1)
              WHERE s.school_id = ? AND c.school_id = ? AND c.is_active = 1 AND sa.id IS NULL
              ORDER BY c.order_index, s.order_index
              LIMIT 8",
            [$schoolId, $schoolId, $schoolId]
        );

        $recent = AuditLog::recent(8);

        $this->render('dashboard/director', [
            'pageTitle' => 'Academic Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'labels'    => array_column($byClass, 'label'),
            'values'    => array_map('intval', array_column($byClass, 'total')),
            'unallocated' => $unallocated,
            'recent'    => $recent,
        ]);
    }

    private function teacher(): void
    {
        $schoolId = school_id();
        $user = Auth::user();
        $staffId = (int)($user['staff_id'] ?? 0);

        // Sessions created before account linking was introduced may not carry
        // staff_id, so read it from the account record when it is missing.
        if ($staffId === 0 && !empty($user['id'])) {
            $row = User::find((int)$user['id']);
            $staffId = (int)($row['staff_id'] ?? 0);
        }
        $staffLinked = $staffId > 0;

        $activeYear = (int)$this->scalar("SELECT id FROM academic_years WHERE school_id = ? AND is_active = 1 LIMIT 1", [$schoolId]);
        $activeTerm = (int)$this->scalar("SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE y.school_id = ? AND y.is_active = 1 AND t.is_active = 1 LIMIT 1", [$schoolId]);

        $allocations = ($activeYear && $staffLinked) ? AcademicManagement::teacherAllocations($schoolId, $activeYear) : [];
        $myAllocations = $staffLinked ? array_filter($allocations, fn (array $a) => (int)$a['staff_id'] === $staffId) : [];

        $classes = array_values(array_unique(array_map(fn (array $a) => $a['class_name'], $myAllocations)));
        $subjects = array_values(array_unique(array_map(fn (array $a) => $a['subject_name'], $myAllocations)));

        $stats = [
            'classes'    => count($classes),
            'subjects'   => count($subjects),
            'allocations'=> count($myAllocations),
            'students'   => $staffLinked && $activeYear
                ? (int)$this->scalar("SELECT COUNT(DISTINCT s.id) FROM students s JOIN teacher_allocations ta ON ta.class_id = s.current_class_id WHERE ta.staff_id = ? AND ta.academic_year_id = ? AND s.status <> 'withdrawn' AND s.school_id = ?", [$staffId, $activeYear, $schoolId])
                : 0,
        ];

        $this->render('dashboard/teacher', [
            'pageTitle' => 'Teacher Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'allocations' => array_slice(array_values($myAllocations), 0, 8),
            'activeYear'  => $activeYear,
            'activeTerm'  => $activeTerm,
            'staffLinked' => $staffLinked,
            'recent'      => AuditLog::recent(8),
        ]);
    }

    private function bursar(): void
    {
        $schoolId = school_id();

        $stats = [
            'invoices'  => (int)$this->scalar("SELECT COUNT(*) FROM fee_invoices WHERE school_id = ?", [$schoolId]),
            'outstanding'=> (float)$this->scalar("SELECT IFNULL(SUM(balance), 0) FROM fee_invoices WHERE school_id = ? AND status <> 'void'", [$schoolId]),
            'paid'      => (float)$this->scalar("SELECT IFNULL(SUM(paid_amount), 0) FROM fee_invoices WHERE school_id = ?", [$schoolId]),
            'pending'   => (int)$this->scalar("SELECT COUNT(*) FROM payments WHERE school_id = ? AND status = 'pending'", [$schoolId]),
        ];

        $debtors = FinanceModel::balances($schoolId, null, false);
        $recentPayments = FinanceModel::payments($schoolId);

        $this->render('dashboard/bursar', [
            'pageTitle' => 'Finance Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'debtors'   => array_slice($debtors, 0, 8),
            'payments'  => array_slice($recentPayments, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function registrar(): void
    {
        $schoolId = school_id();

        $stats = [
            'students'   => (int)$this->scalar("SELECT COUNT(*) FROM students WHERE school_id = ? AND status <> 'withdrawn'", [$schoolId]),
            'active'     => (int)$this->scalar("SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'", [$schoolId]),
            'applicants' => (int)$this->scalar("SELECT COUNT(*) FROM admission_applications WHERE school_id = ? AND status = 'pending'", [$schoolId]),
            'classes'    => (int)$this->scalar("SELECT COUNT(*) FROM classes WHERE school_id = ? AND is_active = 1", [$schoolId]),
        ];

        $recent = $this->all(
            "SELECT s.admission_number, s.first_name, s.last_name, s.status, c.name AS class_name, s.created_at
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
              WHERE s.school_id = ?
              ORDER BY s.created_at DESC
              LIMIT 8",
            [$schoolId]
        );

        $this->render('dashboard/registrar', [
            'pageTitle' => 'Registrar Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'recent'    => $recent,
            'audit'     => AuditLog::recent(8),
        ]);
    }

    private function librarian(): void
    {
        $schoolId = school_id();

        $stats = [
            'books'          => (int)$this->scalar("SELECT COUNT(*) FROM books WHERE school_id = ?", [$schoolId]),
            'total_copies'   => (int)$this->scalar("SELECT IFNULL(SUM(total_copies), 0) FROM books WHERE school_id = ?", [$schoolId]),
            'available'      => (int)$this->scalar("SELECT IFNULL(SUM(available_copies), 0) FROM books WHERE school_id = ?", [$schoolId]),
            'overdue'        => (int)count(LibraryModule::overdueBorrowings($schoolId)),
        ];

        $borrowings = LibraryModule::activeBorrowings($schoolId);
        $overdue = LibraryModule::overdueBorrowings($schoolId);

        $this->render('dashboard/librarian', [
            'pageTitle' => 'Library Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'borrowings' => array_slice($borrowings, 0, 8),
            'overdue'   => array_slice($overdue, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function nurse(): void
    {
        $schoolId = school_id();

        $stats = [
            'students'     => (int)$this->scalar("SELECT COUNT(*) FROM students WHERE school_id = ? AND status IN ('active','suspended')", [$schoolId]),
            'sickbay_today'=> (int)$this->scalar("SELECT COUNT(*) FROM sickbay_visits WHERE school_id = ? AND DATE(visit_date) = CURDATE()", [$schoolId]),
            'visits'       => (int)$this->scalar("SELECT COUNT(*) FROM sickbay_visits WHERE school_id = ?", [$schoolId]),
            'confidential' => (int)$this->scalar("SELECT COUNT(*) FROM medical_records m JOIN students s ON s.id = m.student_id WHERE s.school_id = ? AND m.is_confidential = 1", [$schoolId]),
        ];

        $visits = HealthModule::sickbayVisits($schoolId);

        $this->render('dashboard/nurse', [
            'pageTitle' => 'Health Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'visits'    => array_slice($visits, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function warden(): void
    {
        $schoolId = school_id();

        $stats = [
            'dormitories' => (int)$this->scalar("SELECT COUNT(*) FROM dormitories WHERE school_id = ? AND status = 'active'", [$schoolId]),
            'registrations'=> (int)$this->scalar("SELECT COUNT(*) FROM boarding_registrations r JOIN students s ON s.id = r.student_id WHERE s.school_id = ?", [$schoolId]),
            'checked_in'   => (int)$this->scalar("SELECT COUNT(*) FROM boarding_registrations r JOIN students s ON s.id = r.student_id WHERE s.school_id = ? AND r.status = 'checked_in'", [$schoolId]),
            'boarders'     => (int)$this->scalar("SELECT COUNT(*) FROM boarding_registrations r JOIN students s ON s.id = r.student_id WHERE s.school_id = ? AND r.status <> 'checked_out'", [$schoolId]),
        ];

        $registrations = BoardingModule::registrations($schoolId);

        $this->render('dashboard/warden', [
            'pageTitle' => 'Boarding Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'registrations' => array_slice($registrations, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function gate(): void
    {
        $schoolId = school_id();

        $stats = [
            'visitors_today'=> (int)$this->scalar("SELECT COUNT(*) FROM visitors WHERE school_id = ? AND DATE(time_in) = CURDATE()", [$schoolId]),
            'inside'       => (int)$this->scalar("SELECT COUNT(*) FROM visitors WHERE school_id = ? AND status = 'inside'", [$schoolId]),
            'total'        => (int)$this->scalar("SELECT COUNT(*) FROM visitors WHERE school_id = ?", [$schoolId]),
            'checked_out'  => (int)$this->scalar("SELECT COUNT(*) FROM visitors WHERE school_id = ? AND status = 'out'", [$schoolId]),
        ];

        $visitors = GateModule::visitors($schoolId);

        $this->render('dashboard/gate', [
            'pageTitle' => 'Gate Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'visitors'  => array_slice($visitors, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function hr(): void
    {
        $schoolId = school_id();

        $stats = [
            'staff'      => (int)$this->scalar("SELECT COUNT(*) FROM staff WHERE school_id = ? AND employment_status IN ('active','probation','contract')", [$schoolId]),
            'pending_leave'=> (int)$this->scalar("SELECT COUNT(*) FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id WHERE s.school_id = ? AND lr.status = 'pending'", [$schoolId]),
            'leave_total' => (int)$this->scalar("SELECT COUNT(*) FROM leave_requests lr JOIN staff s ON s.id = lr.staff_id WHERE s.school_id = ?", [$schoolId]),
            'departments' => (int)$this->scalar("SELECT COUNT(*) FROM departments WHERE school_id = ? AND is_active = 1", [$schoolId]),
        ];

        $leaveRequests = HrModule::leaveRequests($schoolId);

        $this->render('dashboard/hr', [
            'pageTitle' => 'HR Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'leave'     => array_slice($leaveRequests, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function storekeeper(): void
    {
        $schoolId = school_id();

        $stats = [
            'items'     => (int)$this->scalar("SELECT COUNT(*) FROM items WHERE school_id = ?", [$schoolId]),
            'low_stock' => (int)$this->scalar("SELECT COUNT(*) FROM items WHERE school_id = ? AND current_stock <= reorder_level AND status = 'active'", [$schoolId]),
            'requests'  => (int)$this->scalar("SELECT COUNT(*) FROM purchase_requests WHERE school_id = ?", [$schoolId]),
            'pending'   => (int)$this->scalar("SELECT COUNT(*) FROM purchase_requests WHERE school_id = ? AND status = 'draft'", [$schoolId]),
        ];

        $items = InventoryModule::items($schoolId);
        $requests = InventoryModule::requests($schoolId);

        $this->render('dashboard/storekeeper', [
            'pageTitle' => 'Inventory Dashboard',
            'activeNav' => 'dashboard',
            'stats'     => $stats,
            'items'     => array_slice($items, 0, 8),
            'requests'  => array_slice($requests, 0, 8),
            'recent'    => AuditLog::recent(8),
        ]);
    }

    private function unassigned(): void
    {
        $user = Auth::user();

        $this->render('dashboard/unassigned', [
            'pageTitle' => 'Dashboard',
            'activeNav' => 'dashboard',
            'user'      => $user,
        ]);
    }
}
