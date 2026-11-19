<?php
declare(strict_types=1);

final class GuardianController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $search   = trim((string)($_GET['search'] ?? ''));
        $editId   = (int)($_GET['edit'] ?? 0);

        $this->render('guardians/index', [
            'pageTitle' => 'Parents & guardians',
            'activeNav' => 'guardians',
            'guardians' => Guardian::listForSchool($schoolId, $search),
            'search'    => $search,
            'edit'      => $editId > 0 ? Guardian::find($editId) : null,
            'canAdd'    => Auth::can('students', 'add'),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'guardians', [], $errors);
        }

        $data['school_id'] = school_id();
        $data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $id = Guardian::insert($data);

        audit('students', 'create', 'guardians', $id, 'Added guardian ' . $data['full_name'], null, $data);
        $this->respond(true, 'Guardian ' . $data['full_name'] . ' added.', 'guardians');
    }

    public function update(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = Guardian::find($id);
        if (!$existing || (int)$existing['school_id'] !== school_id()) {
            $this->respond(false, 'That guardian no longer exists.', 'guardians');
        }

        [$data, $errors] = $this->validate($_POST);
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'guardians', [], $errors);
        }

        $data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
        Guardian::updateRow($id, $data);

        audit('students', 'update', 'guardians', $id, 'Updated guardian ' . $data['full_name']);
        $this->respond(true, 'Guardian updated.', 'guardians');
    }

    public function link(): void
    {
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $guardianId = (int)($_POST['guardian_id'] ?? 0);
        $student    = $studentId > 0 ? Student::find($studentId) : null;
        $guardian   = $guardianId > 0 ? Guardian::find($guardianId) : null;

        if (!$student || (int)$student['school_id'] !== school_id()) {
            $this->respond(false, 'Choose a valid student.', 'guardians', [], ['student_id' => 'Required.']);
        }
        if (!$guardian || (int)$guardian['school_id'] !== school_id()) {
            $this->respond(false, 'Choose a valid guardian.', 'guardians', [], ['guardian_id' => 'Required.']);
        }

        $relationship = (string)($_POST['relationship'] ?? 'guardian');
        if (!in_array($relationship, ['father', 'mother', 'guardian', 'sponsor', 'relative', 'other'], true)) {
            $relationship = 'guardian';
        }

        Guardian::link(
            $studentId,
            $guardianId,
            $relationship,
            isset($_POST['is_primary']),
            isset($_POST['is_fee_payer']),
            isset($_POST['lives_with'])
        );

        audit('students', 'link', 'student_guardians', $studentId, 'Linked guardian to student ' . $student['admission_number']);

        $this->respond(true, 'Guardian linked to ' . $student['first_name'] . ' ' . $student['last_name'] . '.', 'students/profile', ['id' => $studentId]);
    }

    public function unlink(): void
    {
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $guardianId = (int)($_POST['guardian_id'] ?? 0);
        $student    = Student::find($studentId);
        $guardian   = Guardian::find($guardianId);
        if (!$student || (int)$student['school_id'] !== school_id()) {
            $this->respond(false, 'That student no longer exists.', 'students');
        }
        if (!$guardian || (int)$guardian['school_id'] !== school_id()) {
            $this->respond(false, 'That guardian no longer exists.', 'students');
        }

        Guardian::unlink($studentId, $guardianId);
        audit('students', 'unlink', 'student_guardians', $studentId, 'Unlinked guardian from student ' . $student['admission_number']);

        $this->respond(true, 'Guardian removed.', 'students/profile', ['id' => $studentId]);
    }

    private function validate(array $input): array
    {
        $errors = [];
        $data   = [];

        $firstName = trim((string)($input['first_name'] ?? ''));
        if ($firstName === '' || mb_strlen($firstName) > 60) {
            $errors['first_name'] = 'Enter the first name.';
        }
        $data['first_name'] = $firstName;

        $lastName = trim((string)($input['last_name'] ?? ''));
        if ($lastName === '' || mb_strlen($lastName) > 60) {
            $errors['last_name'] = 'Enter the last name.';
        }
        $data['last_name'] = $lastName;

        $relationship = (string)($input['relationship'] ?? 'guardian');
        if (!in_array($relationship, ['father', 'mother', 'guardian', 'sponsor', 'relative', 'other'], true)) {
            $errors['relationship'] = 'Choose a valid relationship.';
        }
        $data['relationship'] = $relationship;

        $phone = trim((string)($input['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{6,40}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }
        $data['phone'] = $phone;

        $data['phone_alt'] = trim((string)($input['phone_alt'] ?? ''));

        $email = trim((string)($input['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        $data['email'] = $email;

        $data['occupation'] = trim((string)($input['occupation'] ?? ''));
        $data['address']    = trim((string)($input['address'] ?? ''));
        $data['district']   = trim((string)($input['district'] ?? ''));
        $data['nin']        = trim((string)($input['nin'] ?? ''));
        $data['notes']      = trim((string)($input['notes'] ?? ''));

        $data['is_primary']  = isset($input['is_primary']) ? 1 : 0;
        $data['receive_sms'] = isset($input['receive_sms']) ? 1 : 0;
        $data['receive_email'] = isset($input['receive_email']) ? 1 : 0;

        return [$data, $errors];
    }
}
