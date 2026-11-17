<?php
declare(strict_types=1);

final class HrController extends Controller
{
    private const EMPLOYMENT_TYPES = ['teaching', 'non_teaching'];
    private const EMPLOYMENT_STATUSES = ['active', 'probation', 'contract', 'suspended', 'resigned', 'terminated', 'retired', 'deceased'];

    public function index(): void
    {
        if (!$this->allowed('view')) {
            return;
        }
        $schoolId = school_id();
        $periodId = (int)($_GET['period_id'] ?? 0);
        $this->render('hr/index', [
            'pageTitle' => 'HR & payroll',
            'activeNav' => 'hr',
            'editStaff' => !empty($_GET['edit_staff']) ? HrModule::staff((int)$_GET['edit_staff'], $schoolId) : null,
            'staff' => HrModule::staffForSchool($schoolId),
            'leaveTypes' => HrModule::leaveTypes($schoolId),
            'leaveRequests' => HrModule::leaveRequests($schoolId),
            'periods' => HrModule::payrollPeriods($schoolId),
            'selectedPeriod' => $periodId ? HrModule::payrollPeriod($periodId, $schoolId) : null,
            'payrollItems' => $periodId ? HrModule::payrollItems($periodId, $schoolId) : [],
            'canAdd' => Auth::can('hr', 'add'),
            'canEdit' => Auth::can('hr', 'edit'),
            'canApprove' => Auth::can('hr', 'approve'),
        ]);
    }

    public function storeStaff(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        [$fields, $errors] = $this->staffFields($_POST);
        $number = trim((string)($_POST['staff_number'] ?? ''));
        if ($number === '' || mb_strlen($number) > 40 || HrModule::staffNumberTaken(school_id(), $number)) {
            $errors['staff_number'] = 'Enter a unique staff number (up to 40 characters).';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'hr', [], $errors);
        }
        $fields['school_id'] = school_id();
        $fields['staff_number'] = $number;
        $fields['created_by'] = (int)Auth::user()['id'];
        $id = HrModule::insertInto('staff', $fields);
        audit('hr', 'create', 'staff', $id, 'Created staff record #' . $id);
        $this->respond(true, 'Staff record created.', 'hr');
    }

    public function updateStaff(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $existing = HrModule::staff($id, school_id());
        if (!$existing) {
            $this->respond(false, 'That staff record was not found.', 'hr');
        }
        [$fields, $errors] = $this->staffFields($_POST);
        $number = trim((string)($_POST['staff_number'] ?? ''));
        if ($number === '' || mb_strlen($number) > 40 || HrModule::staffNumberTaken(school_id(), $number, $id)) {
            $errors['staff_number'] = 'Enter a unique staff number (up to 40 characters).';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'hr', [], $errors);
        }
        $fields['staff_number'] = $number;
        HrModule::updateIn('staff', $id, $fields);
        audit('hr', 'update', 'staff', $id, 'Updated staff record #' . $id);
        $this->respond(true, 'Staff record updated.', 'hr');
    }

    public function storeLeaveType(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $days = filter_var($_POST['days_per_year'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name) > 60 || !preg_match('/^[A-Z0-9_-]{1,20}$/', $code)
            || $days === false || $days < 0 || $days > 366) {
            $this->respond(false, 'Enter a valid leave type, code and annual day allowance.', 'hr');
        }
        try {
            $id = HrModule::insertInto('leave_types', [
                'school_id' => school_id(), 'name' => $name, 'code' => $code,
                'days_per_year' => $days, 'is_paid' => isset($_POST['is_paid']) ? 1 : 0,
                'requires_document' => isset($_POST['requires_document']) ? 1 : 0, 'is_active' => 1,
            ]);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'That leave code is already in use.', 'hr');
            }
            throw $error;
        }
        audit('hr', 'create', 'leave_types', $id, 'Created leave type ' . $code);
        $this->respond(true, 'Leave type created.', 'hr');
    }

    public function applyLeave(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $staff = HrModule::staff($staffId, school_id());
        $typeId = (int)($_POST['leave_type_id'] ?? 0);
        $type = HrModule::leaveType($typeId, school_id());
        $start = trim((string)($_POST['start_date'] ?? ''));
        $end = trim((string)($_POST['end_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!$staff || !$type || !(int)$type['is_active'] || !$this->validDate($start) || !$this->validDate($end) || $end < $start) {
            $this->respond(false, 'Choose a valid staff member, leave type and date range.', 'hr');
        }
        if (mb_strlen($reason) > 255) {
            $this->respond(false, 'The reason must be 255 characters or fewer.', 'hr');
        }
        $days = (float)(new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
        $id = HrModule::insertInto('leave_requests', [
            'staff_id' => $staffId, 'leave_type_id' => $typeId, 'start_date' => $start,
            'end_date' => $end, 'days_requested' => $days, 'reason' => $reason ?: null,
            'status' => 'pending',
        ]);
        audit('hr', 'create', 'leave_requests', $id, 'Submitted leave request #' . $id);
        $this->respond(true, 'Leave request submitted.', 'hr');
    }

    public function reviewLeave(): void
    {
        if (!$this->allowed('approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $request = HrModule::leaveRequest($id, school_id());
        $status = (string)($_POST['status'] ?? '');
        $comments = trim((string)($_POST['review_comments'] ?? ''));
        if (!$request || $request['status'] !== 'pending' || !in_array($status, ['approved', 'rejected'], true)
            || mb_strlen($comments) > 255) {
            $this->respond(false, 'The leave request or review details are invalid.', 'hr');
        }
        HrModule::updateIn('leave_requests', $id, [
            'status' => $status, 'reviewed_by' => (int)Auth::user()['id'],
            'reviewed_at' => date('Y-m-d H:i:s'), 'review_comments' => $comments ?: null,
        ]);
        audit('hr', 'review', 'leave_requests', $id, 'Reviewed leave request #' . $id . ' as ' . $status);
        $this->respond(true, 'Leave request ' . $status . '.', 'hr');
    }

    public function createPayrollPeriod(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $year = filter_var($_POST['period_year'] ?? null, FILTER_VALIDATE_INT);
        $month = filter_var($_POST['period_month'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 2000 || $year > 2200 || $month === false || $month < 1 || $month > 12) {
            $this->respond(false, 'Choose a valid payroll year and month.', 'hr');
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
        try {
            $id = HrModule::insertInto('payroll_periods', [
                'school_id' => school_id(), 'period_year' => $year, 'period_month' => $month,
                'period_label' => date('F Y', strtotime($start)), 'start_date' => $start,
                'end_date' => $end, 'status' => 'draft', 'processed_by' => (int)Auth::user()['id'],
                'processed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'A payroll period already exists for that month.', 'hr');
            }
            throw $error;
        }
        audit('hr', 'create', 'payroll_periods', $id, 'Created payroll period ' . date('F Y', strtotime($start)));
        $this->respond(true, 'Draft payroll period created.', 'hr', ['period_id' => $id]);
    }

    public function addPayrollItem(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $periodId = (int)($_POST['payroll_period_id'] ?? 0);
        $period = HrModule::payrollPeriod($periodId, school_id());
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $staff = HrModule::staff($staffId, school_id());
        $basic = $this->money($_POST['basic_pay'] ?? '');
        $allowances = $this->money($_POST['allowances_total'] ?? '0');
        $deductions = $this->money($_POST['other_deductions'] ?? '0');
        if (!$period || !in_array($period['status'], ['draft', 'processing'], true) || !$staff
            || $basic === null || $allowances === null || $deductions === null) {
            $this->respond(false, 'Choose a draft payroll period and staff member and enter valid non-negative amounts.', 'hr');
        }
        $gross = round($basic + $allowances, 2);
        if ($deductions > $gross) {
            $this->respond(false, 'Deductions cannot exceed gross pay.', 'hr');
        }
        $db = Database::conn();
        try {
            $db->beginTransaction();
            $statement = $db->prepare(
                'INSERT INTO payroll_items
                    (payroll_period_id, staff_id, basic_pay, allowances_total, gross_pay,
                     nssf_employee, nssf_employer, paye, other_deductions, total_deductions, net_pay)
                 VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)'
            );
            $statement->execute([$periodId, $staffId, $basic, $allowances, $gross, $deductions, $deductions, $gross - $deductions]);
            $id = (int)$db->lastInsertId();
            if ($period['status'] === 'draft') {
                $db->prepare('UPDATE payroll_periods SET status = \'processing\' WHERE id = ?')->execute([$periodId]);
            }
            $db->commit();
        } catch (PDOException $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'This staff member already has a payroll item in that period.', 'hr', ['period_id' => $periodId]);
            }
            throw $error;
        }
        audit('hr', 'create', 'payroll_items', $id, 'Recorded payroll item for staff #' . $staffId . ' in period #' . $periodId);
        $this->respond(true, 'Payroll item recorded. Statutory tax and contribution calculations are not included.', 'hr', ['period_id' => $periodId]);
    }

    private function staffFields(array $input): array
    {
        $errors = [];
        $first = trim((string)($input['first_name'] ?? ''));
        $last = trim((string)($input['last_name'] ?? ''));
        $type = (string)($input['employment_type'] ?? 'teaching');
        $status = (string)($input['employment_status'] ?? 'active');
        if ($first === '' || mb_strlen($first) > 60) {
            $errors['first_name'] = 'Enter a first name up to 60 characters.';
        }
        if ($last === '' || mb_strlen($last) > 60) {
            $errors['last_name'] = 'Enter a last name up to 60 characters.';
        }
        if (!in_array($type, self::EMPLOYMENT_TYPES, true) || !in_array($status, self::EMPLOYMENT_STATUSES, true)) {
            $errors['employment_status'] = 'Choose a valid employment type and status.';
        }
        $fields = [
            'first_name' => $first, 'middle_name' => $this->bounded($input, 'middle_name', 60, $errors),
            'last_name' => $last, 'phone' => $this->bounded($input, 'phone', 40, $errors),
            'email' => $this->emailField($input, $errors), 'position' => $this->bounded($input, 'position', 120, $errors),
            'highest_qualification' => $this->bounded($input, 'highest_qualification', 140, $errors),
            'employment_type' => $type, 'employment_status' => $status,
            'date_of_appointment' => $this->dateField($input, 'date_of_appointment', $errors),
            'date_of_confirmation' => $this->dateField($input, 'date_of_confirmation', $errors),
            'date_of_leaving' => $this->dateField($input, 'date_of_leaving', $errors),
            'notes' => $this->bounded($input, 'notes', 255, $errors),
        ];
        return [$fields, $errors];
    }

    private function bounded(array $input, string $key, int $max, array &$errors): ?string
    {
        $value = trim((string)($input[$key] ?? ''));
        if (mb_strlen($value) > $max) {
            $errors[$key] = 'Must be ' . $max . ' characters or fewer.';
        }
        return $value !== '' ? $value : null;
    }

    private function emailField(array $input, array &$errors): ?string
    {
        $value = trim((string)($input['email'] ?? ''));
        if ($value !== '' && (mb_strlen($value) > 120 || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        return $value !== '' ? $value : null;
    }

    private function dateField(array $input, string $key, array &$errors): ?string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value !== '' && !$this->validDate($value)) {
            $errors[$key] = 'Enter a valid date.';
        }
        return $value !== '' ? $value : null;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function money(mixed $value): ?float
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string)$value)) {
            return null;
        }
        return (float)$value;
    }

    protected function allowed(string $module = 'hr', string $action = 'view'): bool
    {
        if (Auth::can('hr', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this HR action.', 'dashboard');
        return false;
    }

    public function staffAppointmentLetter(): void
    {
        $schoolId = school_id();
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $db = Database::conn();
        $staff = $db->query(
            'SELECT sf.*, d.name AS department_name
               FROM staff sf
               LEFT JOIN departments d ON d.id = sf.department_id
              WHERE sf.id = ? AND sf.school_id = ?',
            [$staffId, $schoolId]
        )->fetch();
        if (!$staff) {
            $this->respond(false, 'Staff member not found.', 'hr');
            return;
        }
        $school = school_row() ?? [];
        $html = PdfService::staffAppointmentLetterHtml($staff, $school);
        PdfService::output($html, 'appointment_letter_'.$staff['staff_number'].'.pdf');
    }

    public function staffIdCard(): void
    {
        $schoolId = school_id();
        $staffId = (int)($_GET['staff_id'] ?? 0);
        $db = Database::conn();
        $staff = $db->query(
            'SELECT sf.*, d.name AS department_name
               FROM staff sf
               LEFT JOIN departments d ON d.id = sf.department_id
              WHERE sf.id = ? AND sf.school_id = ?',
            [$staffId, $schoolId]
        )->fetch();
        if (!$staff) {
            $this->respond(false, 'Staff member not found.', 'hr');
            return;
        }
        $school = school_row() ?? [];
        $html = PdfService::staffIdCardHtml($staff, $school);
        PdfService::output($html, 'staff_id_'.$staff['staff_number'].'.pdf');
    }
}
