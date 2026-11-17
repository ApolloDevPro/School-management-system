<?php
declare(strict_types=1);

final class AdmissionController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $status   = trim((string)($_GET['status'] ?? ''));

        $this->render('admissions/index', [
            'pageTitle'    => 'Admissions',
            'activeNav'    => 'admissions',
            'applications' => AdmissionApplication::listForSchool($schoolId, $status),
            'status'       => $status,
            'years'        => AcademicYear::forSchool($schoolId),
            'classes'      => SchoolClass::activeForSchool($schoolId),
            'canAdd'       => Auth::can('students', 'add'),
            'canEdit'      => Auth::can('students', 'edit'),
        ]);
    }

    public function store(): void
    {
        $schoolId = school_id();
        $errors   = [];

        $firstName = trim((string)($_POST['first_name'] ?? ''));
        if ($firstName === '') {
            $errors['first_name'] = 'Enter the first name.';
        }
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        if ($lastName === '') {
            $errors['last_name'] = 'Enter the last name.';
        }
        $gender = (string)($_POST['gender'] ?? '');
        if (!in_array($gender, ['male', 'female'], true)) {
            $errors['gender'] = 'Choose the gender.';
        }

        $yearId = (int)($_POST['academic_year_id'] ?? 0);
        $year   = $yearId > 0 ? AcademicYear::find($yearId) : null;
        if (!$year || (int)$year['school_id'] !== $schoolId) {
            $errors['academic_year_id'] = 'Choose a valid academic year.';
        }

        $classId = (int)($_POST['applied_class_id'] ?? 0);
        if ($classId > 0) {
            $class = SchoolClass::find($classId);
            if (!$class || (int)$class['school_id'] !== $schoolId) {
                $errors['applied_class_id'] = 'Choose a valid class.';
            }
        }

        $dob = (string)($_POST['date_of_birth'] ?? '');
        if ($dob !== '' && (!strtotime($dob) || strtotime($dob) > time())) {
            $errors['date_of_birth'] = 'Enter a valid date of birth.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'admissions', [], $errors);
        }

        $source = (string)($_POST['source'] ?? 'walk_in');
        if (!in_array($source, ['walk_in', 'website', 'transfer', 'recommendation', 'other'], true)) {
            $source = 'walk_in';
        }

        $data = [
            'school_id'          => $schoolId,
            'academic_year_id'   => (int)$year['id'],
            'term_id'            => null,
            'application_number' => Sequence::next($schoolId, 'application_number'),
            'first_name'         => $firstName,
            'middle_name'        => trim((string)($_POST['middle_name'] ?? '')) ?: null,
            'last_name'          => $lastName,
            'gender'             => $gender,
            'date_of_birth'      => $dob !== '' && strtotime($dob) ? $dob : null,
            'previous_school'    => trim((string)($_POST['previous_school'] ?? '')) ?: null,
            'previous_result'    => trim((string)($_POST['previous_result'] ?? '')) ?: null,
            'applied_class_id'   => $classId > 0 ? $classId : null,
            'guardian_name'      => trim((string)($_POST['guardian_name'] ?? '')) ?: null,
            'guardian_phone'     => trim((string)($_POST['guardian_phone'] ?? '')) ?: null,
            'guardian_email'     => trim((string)($_POST['guardian_email'] ?? '')) ?: null,
            'source'             => $source,
            'notes'              => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        $id = AdmissionApplication::insert($data);
        audit('students', 'create', 'admission_applications', $id, 'Received application ' . $data['application_number'], null, $data);

        $this->respond(true, 'Application ' . $data['application_number'] . ' recorded.', 'admissions');
    }

    public function decide(): void
    {
        $schoolId = school_id();
        $id       = (int)($_POST['id'] ?? 0);
        $app      = AdmissionApplication::find($id);
        if (!$app || (int)$app['school_id'] !== $schoolId) {
            $this->respond(false, 'That application no longer exists.', 'admissions');
        }

        $decision = (string)($_POST['decision'] ?? '');
        if (!in_array($decision, ['admitted', 'rejected', 'waitlisted', 'withdrawn'], true)) {
            $this->respond(false, 'Choose a valid decision.', 'admissions');
        }

        $classId = (int)($_POST['class_id'] ?? 0);
        if ($classId === 0) {
            $classId = (int)($app['applied_class_id'] ?? 0);
        }
        if ($decision === 'admitted') {
            if ($classId <= 0) {
                $this->respond(false, 'Choose the class to admit the student into.', 'admissions', [], ['class_id' => 'Required.']);
            }
            $class = SchoolClass::find($classId);
            if (!$class || (int)$class['school_id'] !== $schoolId) {
                $this->respond(false, 'Choose a valid class for admission.', 'admissions');
            }
        }

        $userId = (int)Auth::user()['id'];
        $db     = Database::conn();
        $db->beginTransaction();

        try {
            $fields = [
                'status'         => $decision,
                'decided_by'     => $userId,
                'decided_at'     => date('Y-m-d H:i:s'),
                'decision_notes' => trim((string)($_POST['decision_notes'] ?? '')) ?: null,
            ];

            $admissionNumber = null;
            $studentId = (int)($app['student_id'] ?? 0);

            if ($decision === 'admitted' && $studentId === 0) {
                $admissionNumber = Sequence::next($schoolId, 'admission_number');
                $year = AcademicYear::find((int)$app['academic_year_id']);

                $studentId = Student::insert([
                    'school_id'           => $schoolId,
                    'admission_number'    => $admissionNumber,
                    'first_name'          => $app['first_name'],
                    'middle_name'         => $app['middle_name'],
                    'last_name'           => $app['last_name'],
                    'gender'              => $app['gender'],
                    'date_of_birth'       => $app['date_of_birth'],
                    'previous_school'     => $app['previous_school'],
                    'previous_result'     => $app['previous_result'],
                    'current_class_id'    => $classId,
                    'admission_date'      => date('Y-m-d'),
                    'academic_year_id'    => (int)$app['academic_year_id'],
                    'status'              => 'active',
                    'created_by'          => $userId,
                ]);

                Student::recordPlacement(
                    $studentId,
                    $classId,
                    null,
                    (int)$app['academic_year_id'],
                    null,
                    'admission',
                    date('Y-m-d'),
                    'Admitted from application ' . $app['application_number'],
                    $userId
                );

                if ($app['guardian_name']) {
                    $parts = explode(' ', trim((string)$app['guardian_name']), 2);
                    $guardianId = Guardian::insert([
                        'school_id'    => $schoolId,
                        'first_name'   => $parts[0],
                        'last_name'    => $parts[1] ?? $parts[0],
                        'full_name'    => $app['guardian_name'],
                        'relationship' => 'guardian',
                        'phone'        => $app['guardian_phone'],
                        'email'        => $app['guardian_email'],
                        'is_primary'   => 1,
                    ]);
                    Guardian::link($studentId, $guardianId, 'guardian', true, true, false);
                }

                $fields['student_id']      = $studentId;
                $fields['admission_number'] = $admissionNumber;
            }

            AdmissionApplication::updateRow($id, $fields);
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        $message = 'Application marked as ' . $decision . '.';
        if ($admissionNumber) {
            $message = 'Student admitted with admission number ' . $admissionNumber . '.';
        }

        audit('students', 'decide', 'admission_applications', $id, $message);

        $this->respond(true, $message, 'admissions');
    }

    public function admissionLetter(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $db = Database::conn();
        $student = $db->prepare(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name, h.name AS house_name,
                    y.name AS academic_year
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
               LEFT JOIN houses h ON h.id = s.house_id
               LEFT JOIN academic_years y ON y.id = s.academic_year_id
              WHERE s.id = ? AND s.school_id = ?'
        );
        $student->execute([$studentId, $schoolId]);
        $student = $student->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'admissions');
            return;
        }
        $termName = (string)($_GET['term'] ?? '');
        $yearName = $student['academic_year'] ?? '-';
        $school = school_row() ?? [];
        $html = PdfService::admissionLetterHtml($student, $school, [
            'academic_year' => $yearName,
            'term' => $termName,
        ]);
        PdfService::output($html, 'admission_letter_'.$student['admission_number'].'.pdf');
    }
}
