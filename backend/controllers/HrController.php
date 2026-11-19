<?php
declare(strict_types=1);

/**
 * Staff records, leave, payroll, recruitment, appraisals and staff documents.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
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

    public function recruitment(): void
    {
        if (!$this->allowed('hr', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $vacancies = Database::query(
            'SELECT v.*, d.name AS department_name,
                    (SELECT COUNT(*) FROM job_applications ja WHERE ja.vacancy_id = v.id) AS application_count
               FROM vacancies v
               LEFT JOIN departments d ON d.id = v.department_id
              WHERE v.school_id = ?
              ORDER BY v.created_at DESC',
            [$schoolId]
        )->fetchAll();

        $applications = Database::query(
            'SELECT ja.*, v.title AS vacancy_title, v.status AS vacancy_status
               FROM job_applications ja
               LEFT JOIN vacancies v ON v.id = ja.vacancy_id
              WHERE ja.school_id = ?
              ORDER BY ja.applied_at DESC
              LIMIT 200',
            [$schoolId]
        )->fetchAll();

        $staff = HrModule::staffForSchool($schoolId);
        $departments = AcademicManagement::departments($schoolId);

        $this->render('hr/recruitment', [
            'pageTitle' => 'Recruitment',
            'activeNav' => 'hr',
            'vacancies' => $vacancies,
            'applications' => $applications,
            'staff' => $staff,
            'departments' => $departments,
            'canAdd' => Auth::can('hr', 'add'),
            'canApprove' => Auth::can('hr', 'approve'),
        ]);
    }

    public function saveVacancy(): void
    {
        if (!$this->allowed('hr', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $departmentId = $this->optionalInt($_POST['department_id'] ?? null);
        $position = trim((string)($_POST['position'] ?? ''));
        $employmentType = (string)($_POST['employment_type'] ?? 'teaching');
        $slots = filter_var($_POST['slots'] ?? '1', FILTER_VALIDATE_INT);
        $minQualification = trim((string)($_POST['min_qualification'] ?? ''));
        $experienceRequired = trim((string)($_POST['experience_required'] ?? ''));
        $salaryMin = $this->optionalMoney($_POST['salary_min'] ?? null);
        $salaryMax = $this->optionalMoney($_POST['salary_max'] ?? null);
        $publishedDate = $this->optionalDate($_POST['published_date'] ?? null);
        $closingDate = $this->optionalDate($_POST['closing_date'] ?? null);
        $status = (string)($_POST['status'] ?? 'draft');

        if ($title === '' || mb_strlen($title) > 140
            || $position === '' || mb_strlen($position) > 120
            || !in_array($employmentType, ['teaching', 'non_teaching'], true)
            || ($slots === false || $slots < 1 || $slots > 100)
            || mb_strlen($minQualification) > 140
            || mb_strlen($experienceRequired) > 80
            || ($salaryMin !== null && $salaryMin < 0)
            || ($salaryMax !== null && $salaryMax < 0)
            || ($salaryMin !== null && $salaryMax !== null && $salaryMax < $salaryMin)
            || !$this->validDateOpt($publishedDate)
            || !$this->validDateOpt($closingDate)
            || ($publishedDate && $closingDate && $closingDate < $publishedDate)
            || !in_array($status, ['draft', 'open', 'closed', 'filled', 'cancelled'], true)) {
            $this->respond(false, 'Enter valid vacancy details.', 'hr');
            return;
        }

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = Database::query('SELECT id FROM vacancies WHERE id = ? AND school_id = ?', [$id, $schoolId])->fetch();
                if (!$exists) {
                    throw new DomainException('Vacancy not found.');
                }
                $db->prepare(
                    'UPDATE vacancies SET title = ?, department_id = ?, position = ?, employment_type = ?,
                        slots = ?, min_qualification = ?, experience_required = ?, salary_min = ?,
                        salary_max = ?, published_date = ?, closing_date = ?, status = ?
                      WHERE id = ? AND school_id = ?'
                )->execute([
                    $title, $departmentId, $position, $employmentType, $slots,
                    $minQualification !== '' ? $minQualification : null,
                    $experienceRequired !== '' ? $experienceRequired : null,
                    $salaryMin, $salaryMax, $publishedDate, $closingDate, $status, $id, $schoolId
                ]);
                audit('hr', 'update_vacancy', 'vacancies', $id, 'Updated vacancy: ' . $title);
                $this->respond(true, 'Vacancy updated.', 'hr');
            } else {
                $db->prepare(
                    'INSERT INTO vacancies
                        (school_id, title, department_id, position, employment_type, slots,
                         min_qualification, experience_required, salary_min, salary_max,
                         published_date, closing_date, status, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $schoolId, $title, $departmentId, $position, $employmentType, $slots,
                    $minQualification !== '' ? $minQualification : null,
                    $experienceRequired !== '' ? $experienceRequired : null,
                    $salaryMin, $salaryMax, $publishedDate, $closingDate, $status,
                    (int)(Auth::user()['id'] ?? 0)
                ]);
                $newId = (int)$db->lastInsertId();
                audit('hr', 'create_vacancy', 'vacancies', $newId, 'Created vacancy: ' . $title);
                $this->respond(true, 'Vacancy created.', 'hr');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'hr');
        }
    }

    public function vacancyStatus(): void
    {
        if (!$this->allowed('hr', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['draft', 'open', 'closed', 'filled', 'cancelled'], true)) {
            $this->respond(false, 'Invalid status.', 'hr');
            return;
        }

        $vacancy = Database::query('SELECT title FROM vacancies WHERE id = ? AND school_id = ?', [$id, $schoolId])->fetch();
        if (!$vacancy) {
            $this->respond(false, 'Vacancy not found.', 'hr');
            return;
        }

        $db->prepare('UPDATE vacancies SET status = ? WHERE id = ? AND school_id = ?')
            ->execute([$status, $id, $schoolId]);
        audit('hr', 'vacancy_status', 'vacancies', $id, 'Changed vacancy status to ' . $status . ': ' . $vacancy['title']);
        $this->respond(true, 'Vacancy status updated.', 'hr');
    }

    public function saveApplication(): void
    {
        if (!$this->allowed('hr', 'add')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $id = (int)($_POST['id'] ?? 0);
        $vacancyId = $this->optionalInt($_POST['vacancy_id'] ?? null);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $gender = (string)($_POST['gender'] ?? '');
        $dateOfBirth = $this->optionalDate($_POST['date_of_birth'] ?? null);
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $qualification = trim((string)($_POST['qualification'] ?? ''));
        $experienceYears = filter_var($_POST['experience_years'] ?? null, FILTER_VALIDATE_INT);
        $currentPosition = trim((string)($_POST['current_position'] ?? ''));
        $status = (string)($_POST['status'] ?? 'received');

        if ($fullName === '' || mb_strlen($fullName) > 140
            || ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true))
            || !$this->validDateOpt($dateOfBirth)
            || mb_strlen($phone) > 40
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || mb_strlen($address) > 255
            || mb_strlen($qualification) > 160
            || ($experienceYears === false && $_POST['experience_years'] !== '')
            || ($experienceYears !== null && ($experienceYears < 0 || $experienceYears > 100))
            || mb_strlen($currentPosition) > 120
            || !in_array($status, ['received', 'shortlisted', 'interviewed', 'appointed', 'rejected', 'withdrawn'], true)) {
            $this->respond(false, 'Enter valid application details.', 'hr');
            return;
        }

        if ($vacancyId !== null) {
            $vacancy = Database::query('SELECT id FROM vacancies WHERE id = ? AND school_id = ?', [$vacancyId, $schoolId])->fetch();
            if (!$vacancy) {
                $this->respond(false, 'Vacancy not found.', 'hr');
                return;
            }
        }

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = Database::query('SELECT id FROM job_applications WHERE id = ? AND school_id = ?', [$id, $schoolId])->fetch();
                if (!$exists) {
                    throw new DomainException('Application not found.');
                }
                $db->prepare(
                    'UPDATE job_applications SET vacancy_id = ?, full_name = ?, gender = ?, date_of_birth = ?,
                        phone = ?, email = ?, address = ?, qualification = ?, experience_years = ?,
                        current_position = ?, status = ?
                      WHERE id = ? AND school_id = ?'
                )->execute([
                    $vacancyId, $fullName, $gender !== '' ? $gender : null, $dateOfBirth,
                    $phone !== '' ? $phone : null, $email !== '' ? $email : null,
                    $address !== '' ? $address : null, $qualification !== '' ? $qualification : null,
                    $experienceYears, $currentPosition !== '' ? $currentPosition : null, $status, $id, $schoolId
                ]);
                audit('hr', 'update_application', 'job_applications', $id, 'Updated application: ' . $fullName);
                $this->respond(true, 'Application updated.', 'hr');
            } else {
                $db->prepare(
                    'INSERT INTO job_applications
                        (vacancy_id, school_id, full_name, gender, date_of_birth, phone, email,
                         address, qualification, experience_years, current_position, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $vacancyId, $schoolId, $fullName, $gender !== '' ? $gender : null, $dateOfBirth,
                    $phone !== '' ? $phone : null, $email !== '' ? $email : null,
                    $address !== '' ? $address : null, $qualification !== '' ? $qualification : null,
                    $experienceYears, $currentPosition !== '' ? $currentPosition : null, $status
                ]);
                $newId = (int)$db->lastInsertId();
                audit('hr', 'create_application', 'job_applications', $newId, 'Received application: ' . $fullName);
                $this->respond(true, 'Application received.', 'hr');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'hr');
        }
    }

    public function decideApplication(): void
    {
        if (!$this->allowed('hr', 'approve')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $id = (int)($_POST['id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $interviewDate = $this->optionalDate($_POST['interview_date'] ?? null);
        $interviewNotes = trim((string)($_POST['interview_notes'] ?? ''));
        $decisionNotes = trim((string)($_POST['decision_notes'] ?? ''));

        $validActions = ['shortlist', 'interview', 'appoint', 'reject', 'withdraw'];
        if (!in_array($action, $validActions, true)) {
            $this->respond(false, 'Invalid action.', 'hr');
            return;
        }

        $statusMap = [
            'shortlist' => 'shortlisted',
            'interview' => 'interviewed',
            'appoint' => 'appointed',
            'reject' => 'rejected',
            'withdraw' => 'withdrawn',
        ];
        $newStatus = $statusMap[$action];

        $application = Database::query(
            'SELECT ja.*, v.title AS vacancy_title FROM job_applications ja
               LEFT JOIN vacancies v ON v.id = ja.vacancy_id
              WHERE ja.id = ? AND ja.school_id = ?',
            [$id, $schoolId]
        )->fetch();
        if (!$application) {
            $this->respond(false, 'Application not found.', 'hr');
            return;
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                'UPDATE job_applications SET status = ?, interview_date = ?,
                    interview_notes = ?, decision_notes = ?
                  WHERE id = ? AND school_id = ?'
            )->execute([
                $newStatus, $interviewDate, $interviewNotes !== '' ? $interviewNotes : null,
                $decisionNotes !== '' ? $decisionNotes : null, $id, $schoolId
            ]);

            if ($action === 'appoint' && !empty($_POST['appoint_staff_id'] ?? null)) {
                $staffId = (int)$_POST['appoint_staff_id'];
                $db->prepare('UPDATE job_applications SET appointed_staff_id = ? WHERE id = ?')
                    ->execute([$staffId, $id]);
            }

            audit('hr', 'decide_application', 'job_applications', $id,
                'Application ' . $action . ': ' . $application['full_name'] . ' → ' . $newStatus,
                null, ['action' => $action, 'status' => $newStatus]);
            $db->commit();
            $this->respond(true, 'Application decision recorded.', 'hr');
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'hr');
        }
    }

    public function appraisals(): void
    {
        if (!$this->allowed('hr', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $appraisals = Database::query(
            'SELECT sa.*, s.first_name, s.last_name, s.staff_number,
                    TRIM(CONCAT_WS(\' \', ua.first_name, ua.last_name)) AS appraiser_name
               FROM staff_appraisals sa
               JOIN staff s ON s.id = sa.staff_id
               LEFT JOIN staff ua ON ua.id = sa.appraised_by
              WHERE s.school_id = ?
              ORDER BY sa.created_at DESC
              LIMIT 200',
            [$schoolId]
        )->fetchAll();

        $staff = HrModule::staffForSchool($schoolId);

        $this->render('hr/appraisals', [
            'pageTitle' => 'Staff Appraisals',
            'activeNav' => 'hr',
            'appraisals' => $appraisals,
            'staff' => $staff,
            'canAdd' => Auth::can('hr', 'add'),
        ]);
    }

    public function saveAppraisal(): void
    {
        if (!$this->allowed('hr', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $id = (int)($_POST['id'] ?? 0);
        $staffId = $this->positiveInt($_POST['staff_id'] ?? null, 'Staff member');
        $periodLabel = trim((string)($_POST['period_label'] ?? ''));
        $periodStart = $this->optionalDate($_POST['period_start'] ?? null);
        $periodEnd = $this->optionalDate($_POST['period_end'] ?? null);
        $criteria = [];
        $rawCriteria = $_POST['criteria'] ?? [];
        if (is_array($rawCriteria)) {
            foreach ($rawCriteria as $key => $value) {
                $k = trim((string)$key);
                $v = trim((string)$value);
                if ($k !== '') {
                    $criteria[$k] = $v;
                }
            }
        }
        $overallScore = $this->optionalMoney($_POST['overall_score'] ?? null);
        $comments = trim((string)($_POST['comments'] ?? ''));
        $targets = trim((string)($_POST['targets'] ?? ''));
        $recommendations = trim((string)($_POST['recommendations'] ?? ''));
        $appraisedBy = $this->positiveInt($_POST['appraised_by'] ?? null, 'Appraiser');
        $appraisalDate = $this->optionalDate($_POST['appraisal_date'] ?? null);
        $status = (string)($_POST['status'] ?? 'draft');

        if ($staffId < 1 || $periodLabel === '' || mb_strlen($periodLabel) > 60
            || !$this->validDateOpt($periodStart) || !$this->validDateOpt($periodEnd)
            || ($periodStart && $periodEnd && $periodEnd < $periodStart)
            || ($overallScore !== null && ($overallScore < 0 || $overallScore > 100))
            || mb_strlen($comments) > 65535 || mb_strlen($targets) > 65535
            || mb_strlen($recommendations) > 255
            || $appraisedBy < 1
            || !$this->validDateOpt($appraisalDate)
            || !in_array($status, ['draft', 'completed', 'acknowledged', 'disputed'], true)) {
            $this->respond(false, 'Enter valid appraisal details.', 'hr');
            return;
        }

        $staff = Database::query('SELECT id FROM staff WHERE id = ? AND school_id = ?', [$staffId, $schoolId])->fetch();
        if (!$staff) {
            $this->respond(false, 'Staff member not found in this school.', 'hr');
            return;
        }
        $appraiser = Database::query('SELECT id FROM staff WHERE id = ? AND school_id = ?', [$appraisedBy, $schoolId])->fetch();
        if (!$appraiser) {
            $this->respond(false, 'Appraiser not found in this school.', 'hr');
            return;
        }

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = Database::query(
                    'SELECT sa.id FROM staff_appraisals sa JOIN staff s ON s.id = sa.staff_id
                      WHERE sa.id = ? AND s.school_id = ?',
                    [$id, $schoolId]
                )->fetch();
                if (!$exists) {
                    throw new DomainException('Appraisal not found.');
                }
                $db->prepare(
                    'UPDATE staff_appraisals SET staff_id = ?, period_label = ?, period_start = ?,
                        period_end = ?, criteria = ?, overall_score = ?, comments = ?,
                        targets = ?, recommendations = ?, appraised_by = ?, appraisal_date = ?,
                        status = ?
                      WHERE id = ?'
                )->execute([
                    $staffId, $periodLabel, $periodStart, $periodEnd,
                    json_encode($criteria), $overallScore, $comments !== '' ? $comments : null,
                    $targets !== '' ? $targets : null,
                    $recommendations !== '' ? $recommendations : null,
                    $appraisedBy, $appraisalDate, $status, $id
                ]);
                audit('hr', 'update_appraisal', 'staff_appraisals', $id, 'Updated appraisal for staff #' . $staffId);
                $this->respond(true, 'Appraisal updated.', 'hr');
            } else {
                $db->prepare(
                    'INSERT INTO staff_appraisals
                        (staff_id, period_label, period_start, period_end, criteria,
                         overall_score, comments, targets, recommendations, appraised_by,
                         appraisal_date, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $staffId, $periodLabel, $periodStart, $periodEnd, json_encode($criteria),
                    $overallScore, $comments !== '' ? $comments : null,
                    $targets !== '' ? $targets : null,
                    $recommendations !== '' ? $recommendations : null,
                    $appraisedBy, $appraisalDate, $status
                ]);
                $newId = (int)$db->lastInsertId();
                audit('hr', 'create_appraisal', 'staff_appraisals', $newId, 'Created appraisal for staff #' . $staffId);
                $this->respond(true, 'Appraisal created.', 'hr');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'hr');
        }
    }

    private function optionalMoney(mixed $value): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string)$value)) {
            return null;
        }
        return (float)$value;
    }

    private function validDateOpt(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
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
        $staff = Database::query(
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
        $staff = Database::query(
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
