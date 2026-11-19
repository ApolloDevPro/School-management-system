<?php
declare(strict_types=1);

/**
 * Medical records, sick bay visits and referral forms.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class HealthController extends Controller
{
    private const DISABILITIES = ['none', 'physical', 'visual', 'hearing', 'intellectual', 'multiple'];
    private const REFERRALS = ['none', 'hospital', 'specialist', 'parent', 'clinic'];
    private const VISIT_STATUSES = ['in_progress', 'discharged', 'referred', 'admitted'];

    public function index(): void
    {
        if (!$this->allowed('view')) {
            return;
        }
        $schoolId = school_id();
        $canConfidential = Auth::can('health', 'edit');
        $this->render('health/index', [
            'pageTitle' => 'Health & student welfare',
            'activeNav' => 'health',
            'students' => HealthModule::students($schoolId),
            'medicalRecords' => HealthModule::medicalRecords($schoolId, $canConfidential),
            'visits' => HealthModule::sickbayVisits($schoolId),
            'canAdd' => Auth::can('health', 'add'),
            'canEdit' => $canConfidential,
            'canConfidential' => $canConfidential,
        ]);
    }

    public function saveMedical(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        if (!HealthModule::student($studentId, $schoolId)) {
            $this->respond(false, 'Choose a valid student in this school.', 'health');
        }
        $bloodGroup = $this->bounded('blood_group', 10);
        $disability = (string)($_POST['disability_status'] ?? 'none');
        $errors = [];
        $fields = [
            'blood_group' => $bloodGroup,
            'allergies' => $this->text('allergies', 10000, $errors),
            'chronic_conditions' => $this->text('chronic_conditions', 10000, $errors),
            'current_medication' => $this->text('current_medication', 10000, $errors),
            'disability_status' => $disability,
            'disability_notes' => $this->bounded('disability_notes', 255, $errors),
            'restricted_note' => $this->text('restricted_note', 10000, $errors),
            'is_confidential' => isset($_POST['is_confidential']) ? 1 : 0,
            'updated_by' => (int)Auth::user()['id'],
        ];
        if (!in_array($disability, self::DISABILITIES, true)) {
            $errors['disability_status'] = 'Choose a valid disability status.';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the medical record fields.', 'health', [], $errors);
        }
        $existing = HealthModule::medicalRecordForStudent($studentId);
        if ($existing) {
            HealthModule::updateMedical($studentId, $fields);
            $medicalId = (int)$existing['id'];
            $action = 'update';
        } else {
            $medicalId = HealthModule::insertInto('medical_records', ['student_id' => $studentId, ...$fields]);
            $action = 'create';
        }
        audit('health', $action, 'medical_records', $medicalId, 'Updated restricted medical record for student #' . $studentId);
        $this->respond(true, 'Medical record saved.', 'health');
    }

    public function recordVisit(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $date = trim((string)($_POST['visit_date'] ?? ''));
        $time = trim((string)($_POST['time_in'] ?? ''));
        $referral = (string)($_POST['referred_to'] ?? 'none');
        $status = (string)($_POST['status'] ?? 'discharged');
        $symptoms = $this->text('symptoms', 20000, $errors);
        $treatment = $this->text('treatment_given', 20000, $errors);
        $errors = [];
        $diagnosis = $this->bounded('diagnosis', 500, $errors);
        $temperatureInput = trim((string)($_POST['temperature_c'] ?? ''));
        $temperature = $temperatureInput === '' ? null : filter_var($temperatureInput, FILTER_VALIDATE_FLOAT);
        if (!HealthModule::student($studentId, $schoolId) || !$this->validDate($date) || !$this->validTime($time)
            || !in_array($referral, self::REFERRALS, true) || !in_array($status, self::VISIT_STATUSES, true)
            || ($temperatureInput !== '' && ($temperature === false || $temperature < 25 || $temperature > 50))
            || $errors) {
            $this->respond(false, 'Enter a valid student, visit date, time and sickbay details.', 'health', [], $errors);
        }
        $id = HealthModule::insertInto('sickbay_visits', [
            'school_id' => $schoolId, 'student_id' => $studentId,
            'visit_date' => $date, 'time_in' => $time,
            'symptoms' => $symptoms, 'temperature_c' => $temperature,
            'diagnosis' => $diagnosis, 'treatment_given' => $treatment,
            'seen_by' => (int)(Auth::user()['staff_id'] ?? 0) ?: null,
            'parent_informed' => isset($_POST['parent_informed']) ? 1 : 0,
            'parent_informed_at' => isset($_POST['parent_informed']) ? date('Y-m-d H:i:s') : null,
            'referred_to' => $referral, 'status' => $status,
        ]);
        audit('health', 'create', 'sickbay_visits', $id, 'Recorded restricted sickbay visit #' . $id . ' for student #' . $studentId);
        $this->respond(true, 'Sickbay visit recorded.', 'health');
    }

    private function text(string $key, int $max, array &$errors): ?string
    {
        $value = trim((string)($_POST[$key] ?? ''));
        if (mb_strlen($value) > $max) {
            $errors[$key] = 'Must be ' . $max . ' characters or fewer.';
        }
        return $value !== '' ? $value : null;
    }

    private function bounded(string $key, int $max, array &$errors): ?string
    {
        $value = trim((string)($_POST[$key] ?? ''));
        if (mb_strlen($value) > $max) {
            $errors[$key] = 'Must be ' . $max . ' characters or fewer.';
        }
        return $value !== '' ? $value : null;
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validTime(string $value): bool
    {
        $time = DateTimeImmutable::createFromFormat('!H:i', $value);
        return $time !== false && $time->format('H:i') === $value;
    }

    protected function allowed(string $module = 'health', string $action = 'view'): bool
    {
        if (Auth::can('health', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this restricted health action.', 'dashboard');
        return false;
    }

    public function medicalReferralForm(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $db = Database::conn();
        $student = Database::query(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        )->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'health');
            return;
        }
        $school = school_row() ?? [];
        $html = PdfService::medicalReferralFormHtml($school, $student, [
            'age' => !empty($student['date_of_birth']) ? floor((time() - strtotime($student['date_of_birth'])) / 31556926) : '-',
            'referred_by' => trim((string)($_GET['referred_by'] ?? 'School Nurse')),
            'reason' => trim((string)($_GET['reason'] ?? '')),
            'symptoms' => trim((string)($_GET['symptoms'] ?? '')),
            'facility' => trim((string)($_GET['facility'] ?? 'Nearest hospital')),
            'urgency' => trim((string)($_GET['urgency'] ?? 'Routine')),
        ]);
        PdfService::output($html, 'medical_referral_'.$student['admission_number'].'.pdf');
    }
}
