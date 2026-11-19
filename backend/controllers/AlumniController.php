<?php
declare(strict_types=1);

/**
 * Alumni register and record updates.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class AlumniController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $search   = trim((string)($_GET['search'] ?? ''));
        $editId   = (int)($_GET['edit'] ?? 0);

        $this->render('students/alumni', [
            'pageTitle' => 'Alumni',
            'activeNav' => 'students_alumni',
            'alumni'    => Alumni::listForSchool($schoolId, $search),
            'search'    => $search,
            'edit'      => $editId > 0 ? Alumni::find($editId) : null,
            'graduates' => array_merge(
                Student::listForSchool($schoolId, ['status' => 'completed']),
                Student::listForSchool($schoolId, ['status' => 'graduated'])
            ),
            'canAdd'    => Auth::can('students', 'add'),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function store(): void
    {
        $schoolId = school_id();
        $errors   = [];

        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = $studentId > 0 ? Student::find($studentId) : null;
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            $errors['student_id'] = 'Choose a student.';
        } elseif (Alumni::hasRecord($studentId)) {
            $errors['student_id'] = 'This student already has an alumni record.';
        }

        $year = (int)($_POST['completion_year'] ?? 0);
        if ($year < 1990 || $year > (int)date('Y') + 1) {
            $errors['completion_year'] = 'Enter a valid completion year.';
        }

        $level = (string)($_POST['level_completed'] ?? '');
        if (!in_array($level, ['S4', 'S6'], true)) {
            $errors['level_completed'] = 'Choose S4 or S6.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'students/alumni', [], $errors);
        }

        $id = Alumni::insert([
            'student_id'      => $studentId,
            'completion_year' => $year,
            'level_completed' => $level,
            'current_name'    => trim((string)($_POST['current_name'] ?? '')) ?: null,
            'phone'           => trim((string)($_POST['phone'] ?? '')) ?: null,
            'email'           => trim((string)($_POST['email'] ?? '')) ?: null,
            'current_address' => trim((string)($_POST['current_address'] ?? '')) ?: null,
            'occupation'      => trim((string)($_POST['occupation'] ?? '')) ?: null,
            'further_education' => trim((string)($_POST['further_education'] ?? '')) ?: null,
            'achievements'    => trim((string)($_POST['achievements'] ?? '')) ?: null,
            'is_contactable'  => isset($_POST['is_contactable']) ? 1 : 0,
            'notes'           => trim((string)($_POST['notes'] ?? '')) ?: null,
        ]);

        if (in_array($student['status'], ['completed', 'active'], true)) {
            Student::updateRow($studentId, ['status' => 'graduated']);
        }

        audit('students', 'create', 'alumni', $id, 'Added alumni record for ' . $student['admission_number']);

        $this->respond(true, 'Alumni record created.', 'students/alumni');
    }

    public function update(): void
    {
        $id  = (int)($_POST['id'] ?? 0);
        $row = Alumni::find($id);
        if (!$row) {
            $this->respond(false, 'That alumni record no longer exists.', 'students/alumni');
        }

        $data = [
            'current_name'    => trim((string)($_POST['current_name'] ?? '')) ?: null,
            'phone'           => trim((string)($_POST['phone'] ?? '')) ?: null,
            'email'           => trim((string)($_POST['email'] ?? '')) ?: null,
            'current_address' => trim((string)($_POST['current_address'] ?? '')) ?: null,
            'occupation'      => trim((string)($_POST['occupation'] ?? '')) ?: null,
            'further_education' => trim((string)($_POST['further_education'] ?? '')) ?: null,
            'achievements'    => trim((string)($_POST['achievements'] ?? '')) ?: null,
            'is_contactable'  => isset($_POST['is_contactable']) ? 1 : 0,
            'notes'           => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        if (isset($_POST['testimonial_issued'])) {
            $data['testimonial_issued_at'] = date('Y-m-d H:i:s');
        }

        Alumni::updateRow($id, $data);
        audit('students', 'update', 'alumni', $id, 'Updated alumni record #' . $id);

        $this->respond(true, 'Alumni record updated.', 'students/alumni');
    }
}
