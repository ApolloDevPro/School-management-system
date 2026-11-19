<?php
declare(strict_types=1);

final class StudentController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $filters  = [
            'status'  => trim((string)($_GET['status'] ?? '')),
            'class_id'=> (int)($_GET['class_id'] ?? 0),
            'search'  => trim((string)($_GET['search'] ?? '')),
        ];

        $this->render('students/index', [
            'pageTitle' => 'Students',
            'activeNav' => 'students',
            'students'  => Student::listForSchool($schoolId, $filters),
            'classes'   => SchoolClass::forSchool($schoolId),
            'houses'    => House::activeForSchool($schoolId),
            'filters'   => $filters,
            'canAdd'    => Auth::can('students', 'add'),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'students', [], $errors);
        }

        $schoolId = school_id();
        $admission = trim((string)($_POST['admission_number'] ?? ''));
        if ($admission === '') {
            $admission = Sequence::next($schoolId, 'admission_number');
        } elseif (Student::admissionTaken($schoolId, $admission)) {
            $this->respond(false, 'That admission number is already in use.', 'students', [], ['admission_number' => 'Already in use.']);
        }

        $year   = AcademicYear::active($schoolId);
        $userId = (int)Auth::user()['id'];

        $fields = [
            'school_id'        => $schoolId,
            'admission_number' => $admission,
            'registration_number' => trim((string)($_POST['registration_number'] ?? '')) ?: null,
            'uneb_lin'         => trim((string)($_POST['uneb_lin'] ?? '')) ?: null,
            'nin'              => trim((string)($_POST['nin'] ?? '')) ?: null,
            ...$data,
            'academic_year_id' => $year ? (int)$year['id'] : null,
            'created_by'       => $userId,
        ];

        $photo = $this->handlePhotoUpload(null);
        if ($photo !== null) {
            $fields['photo_path'] = $photo;
        }

        $studentId = Student::insert($fields);

        $classId  = (int)($fields['current_class_id'] ?? 0);
        $streamId = (int)($fields['current_stream_id'] ?? 0);
        if ($classId > 0 && $year) {
            $term = Term::active();
            Student::recordPlacement(
                $studentId,
                $classId,
                $streamId > 0 ? $streamId : null,
                (int)$year['id'],
                $term ? (int)$term['id'] : null,
                'admission',
                date('Y-m-d'),
                'Registered directly',
                $userId
            );
        }

        audit('students', 'create', 'students', $studentId, 'Registered student ' . $admission, null, $fields);

        $this->respond(true, 'Student ' . $admission . ' registered.', 'students/profile', ['id' => $studentId]);
    }

    public function update(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = Student::find($id);
        if (!$existing || (int)$existing['school_id'] !== school_id()) {
            $this->respond(false, 'That student no longer exists.', 'students');
        }

        [$data, $errors] = $this->validate($_POST, $id);
        if (!array_key_exists('current_class_id', $_POST)) {
            $data['current_class_id'] = $existing['current_class_id'];
            $data['current_stream_id'] = $existing['current_stream_id'];
            $data['house_id']          = $existing['house_id'];
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'students/profile', ['id' => $id], $errors);
        }

        $admission = trim((string)($_POST['admission_number'] ?? ''));
        if ($admission === '' || Student::admissionTaken(school_id(), $admission, $id)) {
            $errors['admission_number'] = 'Enter a unique admission number.';
            $this->respond(false, 'Please correct the highlighted fields.', 'students/profile', ['id' => $id], $errors);
        }

        $fields = [
            'admission_number'    => $admission,
            'registration_number' => trim((string)($_POST['registration_number'] ?? '')) ?: null,
            'uneb_lin'            => trim((string)($_POST['uneb_lin'] ?? '')) ?: null,
            'nin'                 => trim((string)($_POST['nin'] ?? '')) ?: null,
            ...$data,
        ];

        $photo = $this->handlePhotoUpload($existing['photo_path'] ?? null);
        if ($photo !== null) {
            $fields['photo_path'] = $photo;
        }

        Student::updateRow($id, $fields);

        $old = [];
        foreach (array_keys($fields) as $key) {
            $old[$key] = $existing[$key] ?? null;
        }

        audit('students', 'update', 'students', $id, 'Updated student ' . $admission, $old, $fields);

        $this->respond(true, 'Student details updated.', 'students/profile', ['id' => $id]);
    }

    public function profile(): void
    {
        $id    = (int)($_GET['id'] ?? 0);
        $student = Student::findFull($id);
        if (!$student || (int)$student['school_id'] !== school_id()) {
            flash_set('warning', 'That student could not be found.');
            redirect('students');
        }

        $schoolId = school_id();
        $year     = AcademicYear::active($schoolId);

        $this->render('students/profile', [
            'pageTitle'  => 'Student profile',
            'activeNav'  => 'students',
            'student'    => $student,
            'guardians'  => Student::guardiansOf($id),
            'subjects'   => $year ? Student::subjectsOf($id, (int)$year['id']) : [],
            'history'    => Student::historyOf($id),
            'incidents'  => DisciplineIncident::forStudent($id),
            'documents'  => StudentDocument::forStudent($id),
            'year'       => $year,
            'classes'    => SchoolClass::activeForSchool($schoolId),
            'houses'     => House::activeForSchool($schoolId),
            'canEdit'    => Auth::can('students', 'edit'),
            'canAdd'     => Auth::can('students', 'add'),
        ]);
    }

    public function allocate(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $student  = Student::find($id);
        if (!$student || (int)$student['school_id'] !== school_id()) {
            $this->respond(false, 'That student no longer exists.', 'students');
        }

        $classId  = (int)($_POST['class_id'] ?? 0);
        $streamId = (int)($_POST['stream_id'] ?? 0);
        $class    = $classId > 0 ? SchoolClass::find($classId) : null;
        if (!$class || (int)$class['school_id'] !== school_id()) {
            $this->respond(false, 'Choose a valid class.', 'students/profile', ['id' => $id], ['class_id' => 'Required.']);
        }

        if ($streamId > 0) {
            $stream = Stream::find($streamId);
            if (!$stream || (int)$stream['class_id'] !== $classId) {
                $this->respond(false, 'Choose a valid stream.', 'students/profile', ['id' => $id], ['stream_id' => 'Invalid stream.']);
            }
        }

        $year  = AcademicYear::active(school_id());
        $term  = Term::active();
        $house = (int)($_POST['house_id'] ?? 0);

        $fields = [
            'current_class_id'  => $classId,
            'current_stream_id' => $streamId > 0 ? $streamId : null,
            'student_type'      => in_array($_POST['student_type'] ?? '', ['day', 'boarding'], true) ? $_POST['student_type'] : $student['student_type'],
            'house_id'          => $house > 0 ? $house : null,
        ];

        Student::updateRow($id, $fields);

        if ($year) {
            Student::recordPlacement(
                $id,
                $classId,
                $streamId > 0 ? $streamId : null,
                (int)$year['id'],
                $term ? (int)$term['id'] : null,
                'reallocation',
                date('Y-m-d'),
                'Allocated from student profile',
                (int)Auth::user()['id']
            );
        }

        audit('students', 'allocate', 'students', $id, 'Allocated ' . $student['admission_number'] . ' to ' . $class['name'], $student, $fields);

        $this->respond(true, $student['admission_number'] . ' allocated to ' . $class['name'] . '.', 'students/profile', ['id' => $id]);
    }

    public function bulkAllocate(): void
    {
        $classId  = (int)($_POST['class_id'] ?? 0);
        $streamId = (int)($_POST['stream_id'] ?? 0);
        $class    = $classId > 0 ? SchoolClass::find($classId) : null;
        if (!$class || (int)$class['school_id'] !== school_id()) {
            $this->respond(false, 'Choose a valid target class.', 'students/allocation', [], ['class_id' => 'Required.']);
        }

        $studentIds = array_map('intval', (array)($_POST['student_ids'] ?? []));
        if (!$studentIds) {
            $this->respond(false, 'Select at least one student to allocate.', 'students/allocation', ['class_id' => $classId]);
        }

        $year = AcademicYear::active(school_id());
        $term = Term::active();
        $userId = (int)Auth::user()['id'];
        $count = 0;

        foreach ($studentIds as $studentId) {
            $student = Student::find($studentId);
            if (!$student || (int)$student['school_id'] !== school_id()) {
                continue;
            }
            Student::assignClass($studentId, $classId, $streamId > 0 ? $streamId : null);
            if ($year) {
                Student::recordPlacement(
                    $studentId,
                    $classId,
                    $streamId > 0 ? $streamId : null,
                    (int)$year['id'],
                    $term ? (int)$term['id'] : null,
                    'reallocation',
                    date('Y-m-d'),
                    'Bulk allocation',
                    $userId
                );
            }
            $count++;
        }

        audit('students', 'bulk_allocate', 'students', $classId, 'Allocated ' . $count . ' student(s) to ' . $class['name']);

        $this->respond(true, $count . ' student(s) allocated to ' . $class['name'] . '.', 'students/allocation', ['class_id' => $classId]);
    }

    public function allocationPage(): void
    {
        $schoolId = school_id();
        $fromClass = (int)($_GET['from_class_id'] ?? 0);
        $toClass   = (int)($_GET['class_id'] ?? 0);

        $this->render('students/allocation', [
            'pageTitle' => 'Class allocation',
            'activeNav' => 'students_allocation',
            'classes'   => SchoolClass::activeForSchool($schoolId),
            'streams'   => $toClass > 0 ? Stream::forClass($toClass) : [],
            'fromClass' => $fromClass,
            'toClass'   => $toClass,
            'students'  => $fromClass > 0 ? Student::byClass($fromClass) : [],
            'houses'    => House::activeForSchool($schoolId),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function withdraw(): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $student = Student::find($id);
        if (!$student || (int)$student['school_id'] !== school_id()) {
            $this->respond(false, 'That student no longer exists.', 'students');
        }

        $date = (string)($_POST['withdrawal_date'] ?? '');
        if ($date === '' || !strtotime($date)) {
            $this->respond(false, 'Choose a valid withdrawal date.', 'students/profile', ['id' => $id], ['withdrawal_date' => 'Required.']);
        }

        $reason = (string)($_POST['reason'] ?? 'other');
        if (!in_array($reason, ['personal', 'academic', 'financial', 'health', 'relocation', 'misconduct', 'other'], true)) {
            $reason = 'other';
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            Withdrawal::insert([
                'student_id'       => $id,
                'withdrawal_date'  => $date,
                'reason'           => $reason,
                'reason_details'   => trim((string)($_POST['reason_details'] ?? '')) ?: null,
                'requested_by'     => trim((string)($_POST['requested_by'] ?? '')) ?: null,
                'approved_by'      => (int)Auth::user()['id'],
                'approved_at'      => date('Y-m-d H:i:s'),
                'outstanding_fees' => 0,
                'remarks'          => trim((string)($_POST['remarks'] ?? '')) ?: null,
            ]);

            Student::updateRow($id, [
                'status'        => 'withdrawn',
                'status_reason' => $reason . ': ' . trim((string)($_POST['reason_details'] ?? '')),
                'leaving_date'  => $date,
            ]);

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'withdraw', 'students', $id, 'Withdrew student ' . $student['admission_number']);

        $this->respond(true, 'Student marked as withdrawn.', 'students/profile', ['id' => $id]);
    }

    private function handlePhotoUpload(?string $existing = null): ?string
    {
        $file = $_FILES['photo'] ?? null;
        if (!$file || !isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            $this->respond(false, 'Photo upload failed. Please try again.', 'students', [], ['photo' => 'Upload failed.']);
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            $this->respond(false, 'Photo must be an image up to 2 MB.', 'students', [], ['photo' => 'Max size is 2 MB.']);
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extMap[$mime])) {
            $this->respond(false, 'Photo must be a JPG, PNG, or WEBP image.', 'students', [], ['photo' => 'Use JPG, PNG, or WEBP.']);
        }
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'students';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = 'student_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mime];
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($tmp, $target)) {
            $this->respond(false, 'Could not save the uploaded photo.', 'students', [], ['photo' => 'Save failed.']);
        }
        if ($existing && strpos($existing, 'uploads/students/') === 0) {
            $old = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existing);
            if (is_file($old)) { @unlink($old); }
        }
        return 'uploads/students/' . $name;
    }

    private function validate(array $input, ?int $ignoreId = null): array
    {
        $errors = [];
        $data   = [];

        $firstName = trim((string)($input['first_name'] ?? ''));
        if ($firstName === '' || mb_strlen($firstName) > 60) {
            $errors['first_name'] = 'Enter the first name.';
        }
        $data['first_name'] = $firstName;

        $data['middle_name'] = trim((string)($input['middle_name'] ?? '')) ?: null;

        $lastName = trim((string)($input['last_name'] ?? ''));
        if ($lastName === '' || mb_strlen($lastName) > 60) {
            $errors['last_name'] = 'Enter the last name.';
        }
        $data['last_name'] = $lastName;

        $gender = (string)($input['gender'] ?? '');
        if (!in_array($gender, ['male', 'female'], true)) {
            $errors['gender'] = 'Choose the gender.';
        }
        $data['gender'] = $gender;

        $dob = (string)($input['date_of_birth'] ?? '');
        if ($dob !== '' && (!strtotime($dob) || strtotime($dob) > time())) {
            $errors['date_of_birth'] = 'Enter a valid date of birth in the past.';
        }
        $data['date_of_birth'] = $dob !== '' && strtotime($dob) ? $dob : null;

        $data['place_of_birth']  = trim((string)($input['place_of_birth'] ?? '')) ?: null;
        $data['nationality']     = trim((string)($input['nationality'] ?? '')) ?: 'Ugandan';
        $data['religion']        = trim((string)($input['religion'] ?? '')) ?: null;
        $data['next_of_kin']     = trim((string)($input['next_of_kin'] ?? '')) ?: null;
        $data['previous_school'] = trim((string)($input['previous_school'] ?? '')) ?: null;
        $data['previous_result'] = trim((string)($input['previous_result'] ?? '')) ?: null;

        $data['special_needs'] = isset($input['special_needs']) ? 1 : 0;
        $data['special_needs_notes'] = trim((string)($input['special_needs_notes'] ?? '')) ?: null;

        $studentType = (string)($input['student_type'] ?? 'day');
        if (!in_array($studentType, ['day', 'boarding'], true)) {
            $studentType = 'day';
        }
        $data['student_type'] = $studentType;

        $classId = (int)($input['current_class_id'] ?? 0);
        if ($classId > 0) {
            $class = SchoolClass::find($classId);
            if (!$class || (int)$class['school_id'] !== school_id()) {
                $errors['current_class_id'] = 'Choose a valid class.';
            }
        }
        $data['current_class_id'] = $classId > 0 ? $classId : null;

        $streamId = (int)($input['current_stream_id'] ?? 0);
        if ($streamId > 0 && $data['current_class_id']) {
            $stream = Stream::find($streamId);
            if (!$stream || (int)$stream['class_id'] !== (int)$data['current_class_id']) {
                $errors['current_stream_id'] = 'Choose a stream in the selected class.';
            }
        }
        $data['current_stream_id'] = $streamId > 0 ? $streamId : null;

        $houseId = (int)($input['house_id'] ?? 0);
        if ($houseId > 0) {
            $house = House::find($houseId);
            if (!$house || (int)$house['school_id'] !== school_id()) {
                $errors['house_id'] = 'Choose a valid house.';
            }
        }
        $data['house_id'] = $houseId > 0 ? $houseId : null;

        $admissionDate = (string)($input['admission_date'] ?? '');
        if ($admissionDate !== '' && !strtotime($admissionDate)) {
            $errors['admission_date'] = 'Enter a valid admission date.';
        }
        $data['admission_date'] = $admissionDate !== '' && strtotime($admissionDate) ? $admissionDate : date('Y-m-d');

        $status = (string)($input['status'] ?? 'active');
        if (!in_array($status, ['applicant', 'active', 'completed', 'transferred_out', 'withdrawn', 'suspended', 'graduated'], true)) {
            $status = 'active';
        }
        $data['status'] = $status;

        return [$data, $errors];
    }

    public function exportCsv(): void
    {
        ImportExportService::studentsExport(school_id());
    }

    public function importCsv(): void
    {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->respond(false, 'Please upload a valid CSV file.', 'students', [], ['csv_file' => 'Required.']);
        }
        $rows = ImportExportService::parseUploadedCsv($file['tmp_name']);
        $result = ImportExportService::importStudents($rows, school_id(), (int)Auth::user()['id']);
        audit('students', 'import', 'students', null, 'Imported students from CSV', null, [
            'created' => $result['created'], 'error_count' => count($result['errors']),
        ]);
        if ($result['errors']) {
            $message = 'No students were imported. ' . count($result['errors']) . ' row(s) need correction: '
                . implode(' ', array_slice($result['errors'], 0, 5));
            $this->respond(false, $message, 'students', [], ['csv_file' => $message]);
        }
        $this->respond(true, 'Imported '.$result['created'].' student(s).', 'students');
    }

    public function studentIdCard(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $db = Database::conn();
        $student = Database::query(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name, h.name AS house_name,
                    y.name AS academic_year
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
               LEFT JOIN houses h ON h.id = s.house_id
               LEFT JOIN academic_years y ON y.id = s.academic_year_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        )->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'students');
            return;
        }
        $school = school_row() ?? [];
        $html = PdfService::studentIdCardHtml($student, $school);
        PdfService::output($html, 'id_card_'.$student['admission_number'].'.pdf');
    }
}
