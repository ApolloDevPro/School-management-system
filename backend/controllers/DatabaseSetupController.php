<?php
declare(strict_types=1);

/**
 * Installs or repairs the reporting views from inside the application.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class DatabaseSetupController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('security', 'edit')) {
            return;
        }

        // Installing views rewrites database objects: require an explicit POST.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->render('database_setup/index', [
                'pageTitle' => 'Database Setup',
                'activeNav' => 'security',
                'created' => [],
                'errors' => [],
            ]);
            return;
        }

        $db = Database::conn();
        $created = [];
        $errors = [];

        $views = [
            'vw_current_term' => $this->sqlCurrentTerm(),
            'vw_student_placement' => $this->sqlStudentPlacement(),
            'vw_student_guardians' => $this->sqlStudentGuardians(),
            'vw_fee_balances' => $this->sqlFeeBalances(),
            'vw_debtor_list' => $this->sqlDebtorList(),
            'vw_fee_collection_summary' => $this->sqlFeeCollectionSummary(),
            'vw_invoice_status' => $this->sqlInvoiceStatus(),
            'vw_attendance_daily_summary' => $this->sqlAttendanceDailySummary(),
            'vw_student_attendance_rate' => $this->sqlStudentAttendanceRate(),
            'vw_staff_attendance_summary' => $this->sqlStaffAttendanceSummary(),
            'vw_subject_results_with_grades' => $this->sqlSubjectResultsWithGrades(),
            'vw_grade_distribution' => $this->sqlGradeDistribution(),
            'vw_exam_class_performance' => $this->sqlExamClassPerformance(),
            'vw_ranked_results' => $this->sqlRankedResults(),
            'vw_certificate_eligibility_status' => $this->sqlCertificateEligibility(),
            'vw_uneb_candidate_readiness' => $this->sqlUnebCandidateReadiness(),
            'vw_outstanding_project_work' => $this->sqlOutstandingProjectWork(),
            'vw_teaching_workload' => $this->sqlTeachingWorkload(),
            'vw_syllabus_coverage_percent' => $this->sqlSyllabusCoveragePercent(),
            'vw_homework_status' => $this->sqlHomeworkStatus(),
            'vw_overdue_books' => $this->sqlOverdueBooks(),
            'vw_library_stock_status' => $this->sqlLibraryStockStatus(),
            'vw_dormitory_occupancy' => $this->sqlDormitoryOccupancy(),
            'vw_route_load' => $this->sqlRouteLoad(),
            'vw_low_stock_items' => $this->sqlLowStockItems(),
            'vw_budget_vs_actual' => $this->sqlBudgetVsActual(),
            'vw_income_expenditure' => $this->sqlIncomeExpenditure(),
            'vw_leave_balance' => $this->sqlLeaveBalance(),
            'vw_payroll_summary' => $this->sqlPayrollSummary(),
            'vw_contract_expiry_alerts' => $this->sqlContractExpiryAlerts(),
            'vw_licence_expiry_alerts' => $this->sqlLicenceExpiryAlerts(),
            'vw_audit_summary' => $this->sqlAuditSummary(),
            'vw_support_ticket_summary' => $this->sqlSupportTicketSummary(),
            'vw_school_dashboard' => $this->sqlSchoolDashboard(),
        ];

        foreach ($views as $name => $sql) {
            try {
                $db->exec("DROP VIEW IF EXISTS `$name`");
                $db->exec($sql);
                $created[] = $name;
            } catch (Throwable $e) {
                $errors[$name] = $e->getMessage();
            }
        }

        audit('security', 'setup', 'database_views', null, 'Installed ' . count($created) . ' database views');

        $this->render('database_setup/index', [
            'pageTitle' => 'Database Setup',
            'activeNav' => 'security',
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    private function sqlCurrentTerm(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_current_term AS
SELECT
  ay.school_id,
  ay.id   AS academic_year_id,
  ay.name AS academic_year,
  t.id    AS term_id,
  t.name  AS term_name,
  t.term_number,
  t.start_date,
  t.end_date
FROM academic_years ay
JOIN terms t ON t.academic_year_id = ay.id AND t.is_active = 1
WHERE ay.is_active = 1
SQL;
    }

    private function sqlStudentPlacement(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_student_placement AS
SELECT
  s.id                AS student_id,
  s.school_id,
  s.admission_number,
  s.first_name,
  s.middle_name,
  s.last_name,
  CONCAT(s.first_name, ' ', IFNULL(CONCAT(s.middle_name, ' '), ''), s.last_name) AS full_name,
  s.gender,
  s.date_of_birth,
  s.status,
  s.student_type,
  s.current_class_id AS class_id,
  c.name  AS class_name,
  c.level AS class_level,
  st.name AS stream_name,
  h.name  AS house_name,
  ay.name AS academic_year,
  t.term_name
FROM students s
LEFT JOIN classes c        ON c.id  = s.current_class_id
LEFT JOIN streams st       ON st.id = s.current_stream_id
LEFT JOIN houses h         ON h.id  = s.house_id
LEFT JOIN academic_years ay ON ay.id = s.academic_year_id
LEFT JOIN vw_current_term  t ON t.school_id = s.school_id
SQL;
    }

    private function sqlStudentGuardians(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_student_guardians AS
SELECT
  sg.student_id,
  g.id   AS guardian_id,
  g.full_name,
  CONCAT(g.first_name, ' ', g.last_name) AS guardian_name,
  sg.relationship,
  g.phone,
  g.email,
  g.occupation,
  g.address,
  sg.is_primary,
  sg.is_fee_payer,
  g.receive_sms,
  g.receive_email,
  g.user_id AS portal_user_id
FROM student_guardians sg
JOIN guardians g ON g.id = sg.guardian_id
SQL;
    }

    private function sqlFeeBalances(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_fee_balances AS
SELECT
  i.school_id,
  i.student_id,
  i.academic_year_id,
  i.term_id,
  COUNT(i.id)                             AS invoice_count,
  SUM(i.subtotal)                         AS total_invoiced,
  SUM(i.discount_total)                   AS total_discounts,
  SUM(i.waived_total)                     AS total_waived,
  SUM(i.total_amount)                     AS total_charged,
  SUM(i.paid_amount)                      AS total_paid,
  SUM(i.balance)                          AS balance,
  SUM(CASE WHEN i.balance > 0 AND i.due_date < CURRENT_DATE
           THEN i.balance ELSE 0 END)     AS overdue_amount,
  MIN(CASE WHEN i.balance > 0 THEN i.due_date END) AS next_due_date
FROM fee_invoices i
WHERE i.status <> 'void'
GROUP BY i.school_id, i.student_id, i.academic_year_id, i.term_id
SQL;
    }

    private function sqlDebtorList(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_debtor_list AS
SELECT
  fb.school_id,
  fb.academic_year_id,
  fb.term_id,
  fb.student_id,
  p.full_name AS student_name,
  p.admission_number,
  c.name AS class_name,
  fb.total_charged,
  fb.total_paid,
  fb.balance,
  fb.overdue_amount,
  DATEDIFF(CURRENT_DATE, fb.next_due_date) AS days_overdue,
  vg.full_name AS primary_guardian,
  vg.phone     AS guardian_phone
FROM vw_fee_balances fb
JOIN vw_student_placement p ON p.student_id = fb.student_id
LEFT JOIN classes c          ON c.id = p.class_id
LEFT JOIN vw_student_guardians vg ON vg.student_id = fb.student_id AND vg.is_primary = 1
WHERE fb.balance > 0
SQL;
    }

    private function sqlFeeCollectionSummary(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_fee_collection_summary AS
SELECT
  p.school_id,
  p.payment_date,
  p.channel,
  pm.name AS method_name,
  COUNT(p.id)        AS payment_count,
  SUM(p.amount_local) AS amount_collected
FROM payments p
LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
WHERE p.status IN ('verified','posted')
GROUP BY p.school_id, p.payment_date, p.channel, pm.name
SQL;
    }

    private function sqlInvoiceStatus(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_invoice_status AS
SELECT
  i.id AS invoice_id,
  i.invoice_number,
  i.school_id,
  i.student_id,
  i.academic_year_id,
  i.term_id,
  i.issue_date,
  i.due_date,
  i.total_amount,
  i.paid_amount,
  i.balance,
  i.status,
  CASE WHEN i.balance <= 0 THEN 'Settled'
       WHEN i.paid_amount > 0 THEN 'Part paid'
       WHEN i.due_date < CURRENT_DATE THEN 'Overdue'
       ELSE 'Unpaid' END AS ageing_status
FROM fee_invoices i
SQL;
    }

    private function sqlAttendanceDailySummary(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_attendance_daily_summary AS
SELECT
  a.school_id,
  a.attendance_date,
  a.class_id,
  c.name  AS class_name,
  a.stream_id,
  st.name AS stream_name,
  COUNT(*)                                   AS marked_count,
  SUM(a.status = 'present')                  AS present_count,
  SUM(a.status = 'absent')                   AS absent_count,
  SUM(a.status = 'late')                     AS late_count,
  SUM(a.status = 'excused')                  AS excused_count,
  ROUND(100.0 * SUM(a.status = 'present') / NULLIF(COUNT(*), 0), 2) AS attendance_percent
FROM student_attendance a
JOIN classes c  ON c.id = a.class_id
LEFT JOIN streams st ON st.id = a.stream_id
WHERE a.record_scope = 'daily'
GROUP BY a.school_id, a.attendance_date, a.class_id, c.name, a.stream_id, st.name
SQL;
    }

    private function sqlStudentAttendanceRate(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_student_attendance_rate AS
SELECT
  a.school_id,
  a.student_id,
  a.academic_year_id,
  a.term_id,
  COUNT(*)                                 AS days_marked,
  SUM(a.status = 'present')                AS days_present,
  SUM(a.status = 'absent')                 AS days_absent,
  SUM(a.status = 'late')                   AS days_late,
  ROUND(100.0 * SUM(a.status = 'present') / NULLIF(COUNT(*), 0), 2) AS attendance_percent
FROM student_attendance a
WHERE a.record_scope = 'daily'
GROUP BY a.school_id, a.student_id, a.academic_year_id, a.term_id
SQL;
    }

    private function sqlStaffAttendanceSummary(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_staff_attendance_summary AS
SELECT
  sa.school_id,
  sa.staff_id,
  st.first_name,
  st.last_name,
  COUNT(*)                       AS days_marked,
  SUM(sa.status = 'present')     AS days_present,
  SUM(sa.status = 'absent')      AS days_absent,
  SUM(sa.status = 'late')        AS days_late,
  SUM(sa.status = 'on_leave')    AS days_on_leave,
  SUM(IFNULL(sa.minutes_late,0)) AS total_minutes_late
FROM staff_attendance sa
JOIN staff st ON st.id = sa.staff_id
GROUP BY sa.school_id, sa.staff_id, st.first_name, st.last_name
SQL;
    }

    private function sqlSubjectResultsWithGrades(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_subject_results_with_grades AS
SELECT
  r.exam_id,
  r.student_id,
  r.subject_id,
  r.class_id,
  r.stream_id,
  sub.name AS subject_name,
  sub.code AS subject_code,
  r.ca_percent,
  r.exam_percent,
  r.project_percent,
  r.weighted_percent,
  r.grade_code,
  r.grade_name,
  gb.descriptor,
  CASE WHEN gb.grade_code IS NOT NULL THEN 'Pass' ELSE 'Unclassified' END AS performance_status
FROM subject_results r
JOIN subjects sub ON sub.id = r.subject_id
LEFT JOIN grade_bands gb
       ON gb.grading_scale_id = r.grading_scale_id
      AND r.weighted_percent BETWEEN gb.min_percent AND gb.max_percent
SQL;
    }

    private function sqlGradeDistribution(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_grade_distribution AS
SELECT
  r.exam_id,
  e.name AS exam_name,
  r.class_id,
  c.name AS class_name,
  r.subject_id,
  sub.name AS subject_name,
  IFNULL(r.grade_code, 'N/A') AS grade_code,
  IFNULL(gb.grade_name, 'Not graded') AS grade_name,
  gb.descriptor,
  COUNT(*) AS student_count
FROM subject_results r
JOIN exams e ON e.id = r.exam_id
JOIN classes c ON c.id = r.class_id
JOIN subjects sub ON sub.id = r.subject_id
LEFT JOIN grade_bands gb
       ON gb.grading_scale_id = r.grading_scale_id
      AND r.weighted_percent BETWEEN gb.min_percent AND gb.max_percent
GROUP BY r.exam_id, e.name, r.class_id, c.name, r.subject_id, sub.name,
         IFNULL(r.grade_code, 'N/A'), IFNULL(gb.grade_name, 'Not graded'), gb.descriptor
SQL;
    }

    private function sqlExamClassPerformance(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_exam_class_performance AS
SELECT
  r.exam_id,
  e.name AS exam_name,
  r.class_id,
  c.name AS class_name,
  r.subject_id,
  sub.name AS subject_name,
  COUNT(*) AS candidates,
  ROUND(AVG(r.weighted_percent), 2) AS average_percent,
  MAX(r.weighted_percent) AS highest_percent,
  MIN(r.weighted_percent) AS lowest_percent,
  SUM(r.weighted_percent >= 50) AS passed_count,
  ROUND(100.0 * SUM(r.weighted_percent >= 50) / NULLIF(COUNT(*), 0), 2) AS pass_rate
FROM subject_results r
JOIN exams e ON e.id = r.exam_id
JOIN classes c ON c.id = r.class_id
JOIN subjects sub ON sub.id = r.subject_id
GROUP BY r.exam_id, e.name, r.class_id, c.name, r.subject_id, sub.name
SQL;
    }

    private function sqlRankedResults(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_ranked_results AS
SELECT
  rs.exam_id,
  rs.student_id,
  rs.class_id,
  rs.stream_id,
  rs.average_percent,
  rs.total_points,
  RANK() OVER (PARTITION BY rs.exam_id, rs.class_id ORDER BY rs.average_percent DESC) AS class_rank,
  RANK() OVER (PARTITION BY rs.exam_id, rs.stream_id ORDER BY rs.average_percent DESC) AS stream_rank
FROM result_summaries rs
SQL;
    }

    private function sqlCertificateEligibility(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_certificate_eligibility_status AS
SELECT
  ce.student_id,
  s.admission_number,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  ce.academic_year_id,
  ce.cycle,
  ce.subjects_taken,
  ce.compulsory_missing,
  ce.has_project_work,
  ce.best_level,
  ce.qualified,
  ce.result_code,
  ce.result_meaning,
  ce.issues,
  ce.checked_at
FROM certificate_eligibility ce
JOIN students s ON s.id = ce.student_id
SQL;
    }

    private function sqlUnebCandidateReadiness(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_uneb_candidate_readiness AS
SELECT
  c.student_id,
  s.admission_number,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  c.index_number,
  l.name AS level_name,
  c.status AS registration_status,
  (SELECT COUNT(*) FROM uneb_candidate_subjects cs WHERE cs.candidate_id = c.id) AS subjects_count,
  CASE
    WHEN c.status = 'withdrawn' THEN 'Candidate withdrawn'
    WHEN c.status = 'completed' THEN 'Completed'
    WHEN (SELECT COUNT(*) FROM uneb_candidate_subjects cs WHERE cs.candidate_id = c.id) < 8 THEN 'Fewer than 8 subjects'
    ELSE 'Ready'
  END AS readiness_issue,
  c.notes AS issues
FROM uneb_candidates c
LEFT JOIN students s ON s.id = c.student_id
JOIN uneb_levels l ON l.id = c.level_id
SQL;
    }

    private function sqlOutstandingProjectWork(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_outstanding_project_work AS
SELECT
  p.student_id,
  s.admission_number,
  CONCAT(s.first_name, ' ', s.last_name) AS student_name,
  p.subject_id,
  sub.name AS subject_name,
  p.academic_year_id,
  p.status,
  p.assessed_date
FROM project_work_records p
JOIN students s ON s.id = p.student_id
JOIN subjects sub ON sub.id = p.subject_id
WHERE p.status IN ('pending','missing')
   OR p.score IS NULL
SQL;
    }

    private function sqlTeachingWorkload(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_teaching_workload AS
SELECT
  st.school_id,
  ta.staff_id,
  st.staff_number,
  CONCAT(st.first_name, ' ', st.last_name) AS teacher_name,
  d.name AS department_name,
  ta.academic_year_id,
  ta.term_id,
  COUNT(*)                         AS allocation_count,
  SUM(ta.lessons_per_week)         AS lessons_per_week,
  COUNT(DISTINCT ta.subject_id)    AS subjects_taught,
  COUNT(DISTINCT ta.class_id)      AS classes_taught
FROM teacher_allocations ta
JOIN staff st ON st.id = ta.staff_id
LEFT JOIN departments d ON d.id = st.department_id
GROUP BY st.school_id, ta.staff_id, st.staff_number, st.first_name, st.last_name,
         d.name, ta.academic_year_id, ta.term_id
SQL;
    }

    private function sqlSyllabusCoveragePercent(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_syllabus_coverage_percent AS
SELECT
  sc.class_id,
  c.name AS class_name,
  sc.stream_id,
  sy.subject_id,
  sub.name AS subject_name,
  MAX(sc.academic_year_id) AS academic_year_id,
  MAX(sc.term_id) AS term_id,
  COUNT(DISTINCT st.id) AS total_topics,
  COUNT(DISTINCT sc.topic_id) AS topics_taught,
  ROUND(100.0 * COUNT(DISTINCT sc.topic_id) / NULLIF(COUNT(DISTINCT st.id), 0), 2) AS coverage_percent
FROM syllabus_topics st
JOIN syllabuses sy ON sy.id = st.syllabus_id
JOIN subjects sub ON sub.id = sy.subject_id
LEFT JOIN syllabus_coverage sc ON sc.topic_id = st.id
LEFT JOIN classes c ON c.id = sc.class_id
GROUP BY sc.class_id, c.name, sc.stream_id, sy.subject_id, sub.name,
         sc.academic_year_id, sc.term_id
SQL;
    }

    private function sqlHomeworkStatus(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_homework_status AS
SELECT
  h.id AS homework_id,
  h.title,
  h.subject_id,
  sub.name AS subject_name,
  h.class_id,
  c.name AS class_name,
  h.staff_id,
  CONCAT(st.first_name, ' ', st.last_name) AS teacher_name,
  h.assigned_date,
  h.due_date,
  h.status,
  COUNT(hs.id) AS expected_submissions,
  SUM(hs.status IN ('submitted','marked')) AS submissions_received,
  ROUND(100.0 * SUM(hs.status IN ('submitted','marked')) / NULLIF(COUNT(hs.id), 0), 2) AS submission_percent
FROM homework h
JOIN subjects sub ON sub.id = h.subject_id
JOIN classes c ON c.id = h.class_id
JOIN staff st ON st.id = h.staff_id
LEFT JOIN homework_submissions hs ON hs.homework_id = h.id
GROUP BY h.id, h.title, h.subject_id, sub.name, h.class_id, c.name,
         h.staff_id, st.first_name, st.last_name, h.assigned_date, h.due_date, h.status
SQL;
    }

    private function sqlOverdueBooks(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_overdue_books AS
SELECT
  b.id AS borrowing_id,
  b.school_id,
  bk.title,
  bc.accession_number,
  b.borrower_type,
  b.student_id,
  CONCAT(s.first_name, ' ', s.last_name) AS borrower_name,
  s.admission_number,
  b.staff_id,
  b.issue_date,
  b.due_date,
  DATEDIFF(CURRENT_DATE, b.due_date) AS days_overdue,
  b.status
FROM borrowings b
JOIN book_copies bc ON bc.id = b.copy_id
JOIN books bk ON bk.id = b.book_id
LEFT JOIN students s ON s.id = b.student_id
WHERE b.return_date IS NULL
  AND b.status IN ('borrowed','overdue')
  AND b.due_date < CURRENT_DATE
SQL;
    }

    private function sqlLibraryStockStatus(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_library_stock_status AS
SELECT
  bk.school_id,
  bk.id AS book_id,
  bk.title,
  bk.author,
  cat.name AS category_name,
  bk.total_copies,
  bk.available_copies,
  bk.lost_copies,
  (bk.total_copies - bk.available_copies - bk.lost_copies) AS copies_out,
  bk.status
FROM books bk
LEFT JOIN book_categories cat ON cat.id = bk.category_id
SQL;
    }

    private function sqlDormitoryOccupancy(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_dormitory_occupancy AS
SELECT
  d.school_id,
  d.id AS dormitory_id,
  d.name AS dormitory_name,
  d.dormitory_type,
  d.capacity,
  d.occupied_beds,
  (d.capacity - d.occupied_beds) AS vacancies,
  ROUND(100.0 * d.occupied_beds / NULLIF(d.capacity, 0), 2) AS occupancy_percent,
  CONCAT(w.first_name, ' ', w.last_name) AS warden_name
FROM dormitories d
LEFT JOIN staff w ON w.id = d.warden_staff_id
SQL;
    }

    private function sqlRouteLoad(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_route_load AS
SELECT
  r.school_id,
  r.id AS route_id,
  r.route_code,
  r.route_name,
  v.plate_number,
  v.seating_capacity,
  COUNT(sra.student_id) AS students_allocated,
  ROUND(100.0 * COUNT(sra.student_id) / NULLIF(v.seating_capacity, 0), 2) AS capacity_percent,
  r.status
FROM routes r
LEFT JOIN vehicles v ON v.id = r.vehicle_id
LEFT JOIN student_route_allocations sra ON sra.route_id = r.id AND sra.status = 'active'
GROUP BY r.school_id, r.id, r.route_code, r.route_name, v.plate_number,
         v.seating_capacity, r.status
SQL;
    }

    private function sqlLowStockItems(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_low_stock_items AS
SELECT
  i.school_id,
  i.id AS item_id,
  i.item_code,
  i.name AS item_name,
  cat.name AS category_name,
  i.unit,
  i.current_stock,
  i.reorder_level,
  (i.reorder_level - i.current_stock) AS shortfall,
  i.status
FROM items i
LEFT JOIN item_categories cat ON cat.id = i.category_id
WHERE i.current_stock <= i.reorder_level
  AND i.is_stocked = 1
  AND i.status = 'active'
SQL;
    }

    private function sqlBudgetVsActual(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_budget_vs_actual AS
SELECT
  b.school_id,
  b.id AS budget_id,
  b.name AS budget_name,
  b.budget_type,
  b.academic_year_id,
  b.status,
  a.account_code,
  a.account_name,
  a.account_type,
  SUM(bl.planned_amount) AS planned_amount,
  SUM(bl.actual_amount)  AS actual_amount,
  SUM(bl.planned_amount - bl.actual_amount) AS variance
FROM budgets b
JOIN budget_lines bl ON bl.budget_id = b.id
JOIN gl_accounts a ON a.id = bl.account_id
GROUP BY b.school_id, b.id, b.name, b.budget_type, b.academic_year_id, b.status,
         a.account_code, a.account_name, a.account_type
SQL;
    }

    private function sqlIncomeExpenditure(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_income_expenditure AS
SELECT
  le.school_id,
  le.entry_date,
  a.account_code,
  a.account_name,
  a.account_type,
  SUM(le.debit)  AS total_debit,
  SUM(le.credit) AS total_credit,
  CASE WHEN a.account_type = 'income' THEN SUM(le.credit) - SUM(le.debit)
       WHEN a.account_type = 'expense' THEN SUM(le.debit) - SUM(le.credit)
       ELSE 0 END AS net_amount
FROM ledger_entries le
JOIN gl_accounts a ON a.id = le.account_id
WHERE le.is_posted = 1
GROUP BY le.school_id, le.entry_date, a.account_code, a.account_name, a.account_type
SQL;
    }

    private function sqlLeaveBalance(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_leave_balance AS
SELECT
  st.school_id,
  lr.staff_id,
  st.staff_number,
  CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
  lt.name AS leave_type,
  lt.days_per_year,
  SUM(CASE WHEN YEAR(lr.start_date) = YEAR(CURRENT_DATE)
            AND lr.status = 'approved' THEN lr.days_requested ELSE 0 END) AS days_taken,
  (lt.days_per_year - SUM(CASE WHEN YEAR(lr.start_date) = YEAR(CURRENT_DATE)
            AND lr.status = 'approved' THEN lr.days_requested ELSE 0 END)) AS days_remaining,
  SUM(CASE WHEN lr.status = 'pending' THEN lr.days_requested ELSE 0 END) AS days_pending
FROM leave_requests lr
JOIN leave_types lt ON lt.id = lr.leave_type_id
JOIN staff st ON st.id = lr.staff_id
GROUP BY st.school_id, lr.staff_id, st.staff_number, st.first_name, st.last_name,
         lt.name, lt.days_per_year
SQL;
    }

    private function sqlPayrollSummary(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_payroll_summary AS
SELECT
  pp.school_id,
  pp.id AS payroll_period_id,
  pp.period_label,
  pp.status,
  COUNT(pi.id) AS employees_paid,
  SUM(pi.basic_pay) AS total_basic,
  SUM(pi.allowances_total) AS total_allowances,
  SUM(pi.gross_pay) AS total_gross,
  SUM(pi.nssf_employee) AS total_nssf_employee,
  SUM(pi.nssf_employer) AS total_nssf_employer,
  SUM(pi.paye) AS total_paye,
  SUM(pi.other_deductions) AS total_other_deductions,
  SUM(pi.net_pay) AS total_net_pay
FROM payroll_periods pp
LEFT JOIN payroll_items pi ON pi.payroll_period_id = pp.id
GROUP BY pp.school_id, pp.id, pp.period_label, pp.status
SQL;
    }

    private function sqlContractExpiryAlerts(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_contract_expiry_alerts AS
SELECT
  st.school_id,
  ct.id AS contract_id,
  ct.staff_id,
  CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
  st.position,
  ct.contract_type,
  ct.start_date,
  ct.end_date,
  DATEDIFF(ct.end_date, CURRENT_DATE) AS days_to_expiry,
  ct.status
FROM contracts ct
JOIN staff st ON st.id = ct.staff_id
WHERE ct.status = 'active'
  AND ct.end_date IS NOT NULL
  AND DATEDIFF(ct.end_date, CURRENT_DATE) <= 90
SQL;
    }

    private function sqlLicenceExpiryAlerts(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_licence_expiry_alerts AS
SELECT
  l.school_id,
  l.id AS licence_id,
  l.licence_type,
  l.licence_number,
  l.issuing_body,
  l.issue_date,
  l.expiry_date,
  DATEDIFF(l.expiry_date, CURRENT_DATE) AS days_to_expiry,
  l.status
FROM licences l
WHERE l.expiry_date IS NOT NULL
  AND DATEDIFF(l.expiry_date, CURRENT_DATE) <= 90
SQL;
    }

    private function sqlAuditSummary(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_audit_summary AS
SELECT
  al.school_id,
  al.user_id,
  al.user_name,
  al.module,
  al.action,
  DATE(al.created_at) AS activity_date,
  COUNT(*) AS action_count
FROM audit_logs al
GROUP BY al.school_id, al.user_id, al.user_name, al.module, al.action,
         DATE(al.created_at)
SQL;
    }

    private function sqlSupportTicketSummary(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_support_ticket_summary AS
SELECT
  st.school_id,
  st.status,
  st.priority,
  COUNT(*) AS ticket_count,
  ROUND(AVG(TIMESTAMPDIFF(HOUR, st.created_at, IFNULL(st.first_response_at, CURRENT_TIMESTAMP))), 2) AS avg_first_response_hours,
  ROUND(AVG(TIMESTAMPDIFF(HOUR, st.created_at, IFNULL(st.resolved_at, CURRENT_TIMESTAMP))), 2) AS avg_resolution_hours,
  AVG(st.satisfaction_rating) AS avg_satisfaction
FROM support_tickets st
GROUP BY st.school_id, st.status, st.priority
SQL;
    }

    private function sqlSchoolDashboard(): string
    {
        return <<<SQL
CREATE OR REPLACE VIEW vw_school_dashboard AS
SELECT
  sc.id AS school_id,
  sc.name AS school_name,
  (SELECT COUNT(*) FROM students s WHERE s.school_id = sc.id AND s.status = 'active') AS active_students,
  (SELECT COUNT(*) FROM students s WHERE s.school_id = sc.id AND s.status = 'active' AND s.student_type = 'boarding') AS boarders,
  (SELECT COUNT(*) FROM staff st WHERE st.school_id = sc.id AND st.employment_status = 'active') AS active_staff,
  (SELECT COUNT(*) FROM classes c WHERE c.school_id = sc.id AND c.is_active = 1) AS active_classes,
  (SELECT IFNULL(SUM(i.balance), 0) FROM fee_invoices i WHERE i.school_id = sc.id AND i.status <> 'void') AS outstanding_fees,
  (SELECT IFNULL(SUM(p.amount_local), 0) FROM payments p WHERE p.school_id = sc.id AND p.status IN ('verified','posted') AND p.payment_date >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')) AS fees_collected_this_month,
  (SELECT COUNT(*) FROM student_attendance a WHERE a.school_id = sc.id AND a.attendance_date = CURRENT_DATE AND a.status = 'absent') AS absent_today,
  (SELECT COUNT(*) FROM support_tickets t WHERE t.school_id = sc.id AND t.status IN ('open','in_progress')) AS open_tickets
FROM schools sc
SQL;
    }
}
