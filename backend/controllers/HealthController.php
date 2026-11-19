<?php
declare(strict_types=1);

/**
 * Medical records, sick bay visits, medication, incidents, counselling and welfare.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class HealthController extends Controller
{
    private const DISABILITIES = ['none', 'physical', 'visual', 'hearing', 'intellectual', 'multiple'];
    private const REFERRALS = ['none', 'hospital', 'specialist', 'parent', 'clinic'];
    private const VISIT_STATUSES = ['in_progress', 'discharged', 'referred', 'admitted'];
    private const MED_ROUTES = ['oral', 'topical', 'injection', 'inhalation', 'eye_drops', 'ear_drops', 'other'];
    private const MED_SOURCES = ['self', 'parent', 'prescription', 'nurse'];
    private const INCIDENT_TYPES = ['accident', 'injury', 'illness', 'emergency', 'food_poisoning', 'allergic_reaction', 'death', 'other'];
    private const INCIDENT_STATUSES = ['reported', 'investigating', 'parent_involved', 'resolved', 'escalated'];
    private const COUNSELLING_CATEGORIES = ['academic', 'personal', 'social', 'emotional', 'career', 'behavioural', 'crisis', 'other'];
    private const COUNSELLING_SOURCES = ['self', 'teacher', 'parent', 'head_teacher', 'peer', 'discipline', 'other'];
    private const COUNSELLING_STATUSES = ['scheduled', 'held', 'follow_up', 'closed', 'referred'];
    private const RISK_LEVELS = ['none', 'low', 'medium', 'high'];
    private const WELFARE_CATEGORIES = ['orphan', 'special_needs', 'bullying', 'lack_of_basic_needs', 'abuse', 'neglect', 'mental_health', 'substance', 'other'];
    private const WELFARE_SUPPORTS = ['counselling', 'uniform', 'feeding', 'scholarship', 'medical', 'family_visit', 'referral', 'other'];
    private const WELFARE_STATUSES = ['open', 'in_progress', 'supported', 'closed', 'referred'];

    public function index(): void
    {
        if (!$this->allowed('view')) {
            return;
        }
        $schoolId = school_id();
        $canConfidential = Auth::can('health', 'edit');
        $tab = (string)($_GET['tab'] ?? 'profiles');
        $this->render('health/index', [
            'pageTitle' => 'Health & student welfare',
            'activeNav' => 'health',
            'tab' => in_array($tab, ['profiles', 'sickbay', 'medication', 'incidents', 'counselling'], true) ? $tab : 'profiles',
            'students' => HealthModule::students($schoolId),
            'staff' => HealthModule::staff($schoolId),
            'medicalRecords' => HealthModule::medicalRecords($schoolId, $canConfidential),
            'visits' => HealthModule::sickbayVisits($schoolId),
            'medications' => $canConfidential ? HealthModule::medications($schoolId) : [],
            'incidents' => HealthModule::incidents($schoolId),
            'counselling' => $canConfidential ? HealthModule::counselling($schoolId) : [],
            'welfare' => $canConfidential ? HealthModule::welfare($schoolId) : [],
            'summary' => HealthModule::summary($schoolId),
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
        $this->respond(true, 'Medical record saved.', 'health', ['tab' => 'profiles']);
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
        $errors = [];
        $symptoms = $this->text('symptoms', 20000, $errors);
        $treatment = $this->text('treatment_given', 20000, $errors);
        $diagnosis = $this->bounded('diagnosis', 500, $errors);
        $temperatureInput = trim((string)($_POST['temperature_c'] ?? ''));
        $temperature = $temperatureInput === '' ? null : filter_var($temperatureInput, FILTER_VALIDATE_FLOAT);
        if (!HealthModule::student($studentId, $schoolId) || !$this->validDate($date) || !$this->validTime($time)
            || !in_array($referral, self::REFERRALS, true) || !in_array($status, self::VISIT_STATUSES, true)
            || ($temperatureInput !== '' && ($temperature === false || $temperature < 25 || $temperature > 50))
            || $errors) {
            $this->respond(false, 'Enter a valid student, visit date, time and sickbay details.', 'health', ['tab' => 'sickbay'], $errors);
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
        $this->respond(true, 'Sickbay visit recorded.', 'health', ['tab' => 'sickbay']);
    }

    // ------------------------------------------------------------------
    // Medication administration
    // ------------------------------------------------------------------

    public function addMedication(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $name = trim((string)($_POST['medication_name'] ?? ''));
        $dosage = trim((string)($_POST['dosage'] ?? ''));
        $route = (string)($_POST['route'] ?? 'oral');
        $source = (string)($_POST['given_by_request'] ?? 'nurse');
        if (!HealthModule::student($studentId, $schoolId) || $name === '' || mb_strlen($name) > 160 || $dosage === '' || mb_strlen($dosage) > 80
            || !in_array($route, self::MED_ROUTES, true) || !in_array($source, self::MED_SOURCES, true)) {
            $this->respond(false, 'Choose a student and enter the medication name and dosage.', 'health', ['tab' => 'medication']);
        }
        $administeredAt = $this->dateTimeOrNull($_POST['administered_at'] ?? '');
        $expiry = $this->dateOrNull($_POST['expiry_date'] ?? '');
        if ($expiry !== null && $expiry < date('Y-m-d')) {
            $this->respond(false, 'The expiry date is already in the past.', 'health', ['tab' => 'medication']);
        }
        $id = HealthModule::addMedication([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'visit_id' => null,
            'medication_name' => $name,
            'dosage' => $dosage,
            'route' => $route,
            'frequency' => $this->line($_POST['frequency'] ?? '', 80),
            'reason' => $this->line($_POST['reason'] ?? '', 255),
            'administered_at' => $administeredAt ?? date('Y-m-d H:i:s'),
            'administered_by' => (int)(Auth::user()['staff_id'] ?? 0) ?: null,
            'given_by_request' => $source,
            'expiry_date' => $expiry,
            'batch_number' => $this->line($_POST['batch_number'] ?? '', 60),
            'reaction_notes' => $this->line($_POST['reaction_notes'] ?? '', 255),
        ]);
        audit('health', 'create', 'medication_records', $id, 'Recorded medication for student #' . $studentId);
        $this->respond(true, 'Medication administration recorded.', 'health', ['tab' => 'medication']);
    }

    // ------------------------------------------------------------------
    // Medical incidents
    // ------------------------------------------------------------------

    public function addIncident(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $date = $this->dateOrNull($_POST['incident_date'] ?? '');
        $type = (string)($_POST['incident_type'] ?? 'injury');
        $status = (string)($_POST['status'] ?? 'reported');
        $description = trim((string)($_POST['description'] ?? ''));
        if ($date === null || $description === ''
            || !in_array($type, self::INCIDENT_TYPES, true) || !in_array($status, self::INCIDENT_STATUSES, true)
            || ($studentId > 0 && !HealthModule::student($studentId, $schoolId))) {
            $this->respond(false, 'Enter the incident date, type and description.', 'health', ['tab' => 'incidents']);
        }
        $cost = null;
        if (($_POST['incident_cost'] ?? '') !== '') {
            if (!is_numeric($_POST['incident_cost']) || (float)$_POST['incident_cost'] < 0) {
                $this->respond(false, 'Enter a valid incident cost or leave it blank.', 'health', ['tab' => 'incidents']);
            }
            $cost = round((float)$_POST['incident_cost'], 2);
        }
        $parentInformed = isset($_POST['parent_informed']) ? 1 : 0;
        $hospital = isset($_POST['referred_to_hospital']) ? 1 : 0;
        $id = HealthModule::addIncident([
            'school_id' => $schoolId,
            'student_id' => $studentId > 0 ? $studentId : null,
            'incident_number' => 'INC-' . date('YmdHis'),
            'incident_date' => $date,
            'incident_time' => $this->timeOrNull($_POST['incident_time'] ?? ''),
            'incident_type' => $type,
            'location' => $this->line($_POST['location'] ?? '', 120),
            'description' => $description,
            'action_taken' => $this->longText($_POST['action_taken'] ?? ''),
            'witnesses' => $this->line($_POST['witnesses'] ?? '', 255),
            'reported_by' => (int)(Auth::user()['staff_id'] ?? 0) ?: null,
            'parent_informed' => $parentInformed,
            'parent_informed_at' => $parentInformed ? date('Y-m-d H:i:s') : null,
            'referred_to_hospital' => $hospital,
            'hospital_name' => $hospital ? $this->line($_POST['hospital_name'] ?? '', 160) : null,
            'transport_used' => $this->line($_POST['transport_used'] ?? '', 120),
            'incident_cost' => $cost,
            'status' => $status,
        ]);
        audit('health', 'create', 'medical_incidents', $id, 'Logged medical incident for student #' . $studentId);
        $this->respond(true, 'Medical incident logged.', 'health', ['tab' => 'incidents']);
    }

    public function setIncidentStatus(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, self::INCIDENT_STATUSES, true)) {
            $this->respond(false, 'Choose a valid incident status.', 'health', ['tab' => 'incidents']);
        }
        $ok = HealthModule::setIncidentStatus($id, school_id(), $status, (int)Auth::user()['id']);
        if ($ok) {
            audit('health', 'update', 'medical_incidents', $id, 'Moved incident #' . $id . ' to ' . $status);
        }
        $this->respond($ok, $ok ? 'Incident status updated.' : 'Incident not found.', 'health', ['tab' => 'incidents']);
    }

    // ------------------------------------------------------------------
    // Counselling sessions
    // ------------------------------------------------------------------

    public function addCounselling(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $date = $this->dateOrNull($_POST['session_date'] ?? '');
        $category = (string)($_POST['category'] ?? 'personal');
        $status = (string)($_POST['status'] ?? 'held');
        $risk = (string)($_POST['risk_level'] ?? 'none');
        $referral = (string)($_POST['referral_source'] ?? '');
        $counsellorId = (int)($_POST['counsellor_staff_id'] ?? 0) ?: (int)(Auth::user()['staff_id'] ?? 0);
        if (!HealthModule::student($studentId, $schoolId) || $date === null || $counsellorId === 0
            || !HealthModule::staffMember($counsellorId, $schoolId)
            || !in_array($category, self::COUNSELLING_CATEGORIES, true)
            || !in_array($status, self::COUNSELLING_STATUSES, true)
            || !in_array($risk, self::RISK_LEVELS, true)
            || ($referral !== '' && !in_array($referral, self::COUNSELLING_SOURCES, true))) {
            $this->respond(false, 'Choose a student, counsellor, date and valid counselling options.', 'health', ['tab' => 'counselling']);
        }
        $duration = trim((string)($_POST['duration_minutes'] ?? ''));
        if ($duration !== '' && (!is_numeric($duration) || (int)$duration < 1 || (int)$duration > 480)) {
            $this->respond(false, 'Session duration must be between 1 and 480 minutes.', 'health', ['tab' => 'counselling']);
        }
        $id = HealthModule::addCounselling([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'session_number' => HealthModule::nextSessionNumber($studentId, $schoolId),
            'session_date' => $date,
            'session_time' => $this->timeOrNull($_POST['session_time'] ?? ''),
            'duration_minutes' => $duration !== '' ? (int)$duration : null,
            'category' => $category,
            'referral_source' => $referral !== '' ? $referral : null,
            'concerns' => $this->longText($_POST['concerns'] ?? ''),
            'observations' => $this->longText($_POST['observations'] ?? ''),
            'action_plan' => $this->longText($_POST['action_plan'] ?? ''),
            'resources_shared' => $this->line($_POST['resources_shared'] ?? '', 255),
            'counsellor_staff_id' => $counsellorId,
            'follow_up_date' => $this->dateOrNull($_POST['follow_up_date'] ?? ''),
            'parent_involved' => isset($_POST['parent_involved']) ? 1 : 0,
            'is_confidential' => isset($_POST['is_confidential']) ? 1 : 0,
            'risk_level' => $risk,
            'status' => $status,
            'closed_date' => $status === 'closed' ? $date : null,
        ]);
        audit('health', 'create', 'counselling_sessions', $id, 'Logged counselling session for student #' . $studentId);
        $this->respond(true, 'Counselling session saved.', 'health', ['tab' => 'counselling']);
    }

    // ------------------------------------------------------------------
    // Welfare and support records
    // ------------------------------------------------------------------

    public function addWelfare(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $category = (string)($_POST['category'] ?? 'other');
        $support = (string)($_POST['support_type'] ?? '');
        $status = (string)($_POST['status'] ?? 'open');
        $date = $this->dateOrNull($_POST['date_identified'] ?? '');
        $description = trim((string)($_POST['description'] ?? ''));
        if (!HealthModule::student($studentId, $schoolId) || $date === null || $description === ''
            || !in_array($category, self::WELFARE_CATEGORIES, true)
            || !in_array($status, self::WELFARE_STATUSES, true)
            || ($support !== '' && !in_array($support, self::WELFARE_SUPPORTS, true))) {
            $this->respond(false, 'Choose a student, category and date, then describe the concern.', 'health', ['tab' => 'counselling']);
        }
        $responsible = (int)($_POST['responsible_staff'] ?? 0);
        if ($responsible > 0 && !HealthModule::staffMember($responsible, $schoolId)) {
            $this->respond(false, 'Choose a responsible staff member from this school.', 'health', ['tab' => 'counselling']);
        }
        $id = HealthModule::addWelfare([
            'school_id' => $schoolId,
            'student_id' => $studentId,
            'category' => $category,
            'description' => $description,
            'date_identified' => $date,
            'identified_by' => (int)(Auth::user()['staff_id'] ?? 0) ?: null,
            'support_provided' => $this->longText($_POST['support_provided'] ?? ''),
            'support_type' => $support !== '' ? $support : null,
            'referred_to' => $this->line($_POST['referred_to'] ?? '', 160),
            'external_agency' => $this->line($_POST['external_agency'] ?? '', 160),
            'follow_up_date' => $this->dateOrNull($_POST['follow_up_date'] ?? ''),
            'status' => $status,
            'responsible_staff' => $responsible > 0 ? $responsible : null,
            'is_confidential' => isset($_POST['is_confidential']) ? 1 : 0,
            'closed_at' => $status === 'closed' ? date('Y-m-d H:i:s') : null,
            'notes' => $this->longText($_POST['notes'] ?? ''),
        ]);
        audit('health', 'create', 'welfare_records', $id, 'Logged welfare record for student #' . $studentId);
        $this->respond(true, 'Welfare record saved.', 'health', ['tab' => 'counselling']);
    }

    public function setWelfareStatus(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, self::WELFARE_STATUSES, true)) {
            $this->respond(false, 'Choose a valid welfare status.', 'health', ['tab' => 'counselling']);
        }
        $ok = HealthModule::setWelfareStatus($id, school_id(), $status);
        if ($ok) {
            audit('health', 'update', 'welfare_records', $id, 'Moved welfare record #' . $id . ' to ' . $status);
        }
        $this->respond($ok, $ok ? 'Welfare status updated.' : 'Welfare record not found.', 'health', ['tab' => 'counselling']);
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

    private function line(mixed $value, int $max): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function longText(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
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

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text !== '' && $this->validDate($text) ? $text : null;
    }

    private function timeOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $time = DateTimeImmutable::createFromFormat('!H:i', $text);
        return $time !== false && $time->format('H:i') === $text ? $text . ':00' : null;
    }

    /**
     * Accept the browser's datetime-local value and store a MySQL datetime.
     */
    private function dateTimeOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $text);
        return $date !== false ? $date->format('Y-m-d H:i:s') : null;
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
