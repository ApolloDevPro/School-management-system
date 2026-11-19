<?php
declare(strict_types=1);

/**
 * School profile, academic years, terms, classes, streams, departments, houses and the audit log.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class SetupController extends Controller
{
    public function profile(): void
    {
        $this->render('setup/profile', [
            'pageTitle' => 'School profile',
            'activeNav' => 'setup_profile',
            'school'    => School::first(),
            'canEdit'   => Auth::can('administration', 'edit'),
        ]);
    }

    public function saveProfile(): void
    {
        $input = $_POST;
        $errors = [];

        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            $errors['name'] = 'Enter the school name (up to 160 characters).';
        }

        $email = trim((string)($input['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        $phone = trim((string)($input['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{6,40}$/', $phone)) {
            $errors['phone'] = 'Enter a valid phone number.';
        }

        $ownership = (string)($input['ownership_type'] ?? 'private');
        if (!in_array($ownership, ['government', 'private', 'church', 'community', 'other'], true)) {
            $ownership = 'private';
        }

        if (!in_array((string)($input['currency'] ?? ''), ['UGX', 'USD', 'EUR', 'GBP', 'KES', 'TZS', 'RWF', 'NGN', 'GHS'], true)) {
            $errors['currency'] = 'Choose a supported currency.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/profile', [], $errors);
        }

        $fields = [
            'name'               => $name,
            'motto'              => trim((string)($input['motto'] ?? '')),
            'physical_address'   => trim((string)($input['physical_address'] ?? '')),
            'postal_address'     => trim((string)($input['postal_address'] ?? '')),
            'phone'              => $phone,
            'alternate_phone'    => trim((string)($input['alternate_phone'] ?? '')),
            'email'              => $email,
            'website'            => trim((string)($input['website'] ?? '')),
            'registration_number'=> trim((string)($input['registration_number'] ?? '')),
            'ownership_type'     => $ownership,
            'emis_code'          => trim((string)($input['emis_code'] ?? '')),
            'uneb_index_number'  => trim((string)($input['uneb_index_number'] ?? '')),
            'district'           => trim((string)($input['district'] ?? '')),
            'sub_county'         => trim((string)($input['sub_county'] ?? '')),
            'currency'           => (string)$input['currency'],
        ];

        $dateEstablished = trim((string)($input['date_established'] ?? ''));
        $fields['date_established'] = $dateEstablished !== '' && strtotime($dateEstablished) ? $dateEstablished : null;

        $schoolId = (int)school_id();

        if (!empty($_FILES['logo']['name'])) {
            $logoError = $this->handleLogoUpload($schoolId);
            if ($logoError !== null) {
                $errors['logo'] = $logoError;
                $this->respond(false, 'Please correct the highlighted fields.', 'setup/profile', [], $errors);
            }
        }

        School::updateRow($schoolId, $fields);
        audit('administration', 'update', 'schools', $schoolId, 'Updated school profile', null, $fields);

        $this->respond(true, 'School profile updated.', 'setup/profile');
    }

    private function handleLogoUpload(int $schoolId): ?string
    {
        $file = $_FILES['logo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'The logo could not be uploaded. Check the file size and try again.';
        }

        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            return 'The logo must be a PNG, JPG, GIF or WEBP image.';
        }

        $dir = dirname(__DIR__, 2) . '/uploads/logo';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return 'The uploads folder could not be created.';
        }

        $filename = 'school-' . $schoolId . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            return 'The logo could not be saved on the server.';
        }

        $old = School::find($schoolId)['logo_path'] ?? null;
        if ($old) {
            $oldPath = dirname(__DIR__, 2) . '/' . ltrim($old, '/');
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        School::updateRow($schoolId, ['logo_path' => 'uploads/logo/' . $filename]);
        return null;
    }

    // ------------------------------------------------------------------
    // Academic years
    // ------------------------------------------------------------------
    public function years(): void
    {
        $schoolId = school_id();
        $editId   = (int)($_GET['edit'] ?? 0);
        $edit     = $editId > 0 ? AcademicYear::find($editId) : null;

        $this->render('setup/years', [
            'pageTitle' => 'Academic years',
            'activeNav' => 'setup_years',
            'years'     => AcademicYear::forSchool($schoolId),
            'edit'      => $edit,
            'canEdit'   => Auth::can('administration', 'edit'),
        ]);
    }

    public function saveYear(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? AcademicYear::find($id) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That academic year no longer exists.', 'setup/years');
        }

        $schoolId = school_id();
        $errors   = [];

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 20) {
            $errors['name'] = 'Enter a short name such as 2026/2027 (up to 20 characters).';
        } elseif (AcademicYear::nameTaken($schoolId, $name, $id ?: null)) {
            $errors['name'] = 'An academic year with this name already exists.';
        }

        $start = (string)($_POST['start_date'] ?? '');
        $end   = (string)($_POST['end_date'] ?? '');
        if ($start === '' || !strtotime($start)) {
            $errors['start_date'] = 'Choose a valid start date.';
        }
        if ($end === '' || !strtotime($end)) {
            $errors['end_date'] = 'Choose a valid end date.';
        }
        if (empty($errors['start_date']) && empty($errors['end_date']) && strtotime($end) <= strtotime($start)) {
            $errors['end_date'] = 'The end date must be after the start date.';
        }

        $status = (string)($_POST['status'] ?? 'planned');
        if (!in_array($status, ['planned', 'active', 'closed'], true)) {
            $status = 'planned';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/years', [], $errors);
        }

        $data = [
            'school_id'  => $schoolId,
            'name'       => $name,
            'start_date' => $start,
            'end_date'   => $end,
            'status'     => $status,
            'notes'      => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        if ($existing) {
            AcademicYear::updateRow($id, $data);
            audit('administration', 'update', 'academic_years', $id, 'Updated academic year ' . $name, $existing, $data);
            $this->respond(true, 'Academic year ' . $name . ' updated.', 'setup/years');
        }

        $newId = AcademicYear::insert($data);
        audit('administration', 'create', 'academic_years', $newId, 'Created academic year ' . $name, null, $data);
        $this->respond(true, 'Academic year ' . $name . ' created.', 'setup/years');
    }

    public function activateYear(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $year   = AcademicYear::find($id);
        $school = school_id();
        if (!$year || (int)$year['school_id'] !== $school) {
            $this->respond(false, 'That academic year no longer exists.', 'setup/years');
        }

        AcademicYear::activate($school, $id);
        audit('administration', 'activate', 'academic_years', $id, 'Set ' . $year['name'] . ' as the active academic year');

        $this->respond(true, $year['name'] . ' is now the active academic year.', 'setup/years');
    }

    // ------------------------------------------------------------------
    // Terms
    // ------------------------------------------------------------------
    public function terms(): void
    {
        $schoolId = school_id();
        $years    = AcademicYear::forSchool($schoolId);
        $yearId   = (int)($_GET['year_id'] ?? 0);

        if ($yearId <= 0) {
            $active = AcademicYear::active($schoolId);
            $yearId = $active ? (int)$active['id'] : ($years ? (int)$years[0]['id'] : 0);
        }

        $editId = (int)($_GET['edit'] ?? 0);
        $edit   = $editId > 0 ? Term::find($editId) : null;

        $this->render('setup/terms', [
            'pageTitle' => 'Terms',
            'activeNav' => 'setup_terms',
            'years'     => $years,
            'yearId'    => $yearId,
            'terms'     => $yearId > 0 ? Term::forYear($yearId) : [],
            'edit'      => $edit,
            'canEdit'   => Auth::can('administration', 'edit'),
        ]);
    }

    public function saveTerm(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? Term::find($id) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That term no longer exists.', 'setup/terms');
        }

        $errors  = [];
        $yearId  = (int)($_POST['academic_year_id'] ?? 0);
        $year    = $yearId > 0 ? AcademicYear::find($yearId) : null;
        if (!$year || (int)$year['school_id'] !== school_id()) {
            $errors['academic_year_id'] = 'Choose a valid academic year.';
        }

        $name   = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 30) {
            $errors['name'] = 'Enter the term name (up to 30 characters), e.g. Term 1.';
        }

        $number = (int)($_POST['term_number'] ?? 0);
        if ($number < 1 || $number > 4) {
            $errors['term_number'] = 'Choose a term number between 1 and 4.';
        } elseif ($year && Term::numberTaken($yearId, $number, $id ?: null)) {
            $errors['term_number'] = 'This term number already exists for the selected year.';
        }

        $start = (string)($_POST['start_date'] ?? '');
        $end   = (string)($_POST['end_date'] ?? '');
        if ($start === '' || !strtotime($start)) {
            $errors['start_date'] = 'Choose a valid start date.';
        }
        if ($end === '' || !strtotime($end)) {
            $errors['end_date'] = 'Choose a valid end date.';
        }
        if (empty($errors['start_date']) && empty($errors['end_date']) && strtotime($end) <= strtotime($start)) {
            $errors['end_date'] = 'The end date must be after the start date.';
        }

        $report = trim((string)($_POST['report_date'] ?? ''));
        if ($report !== '' && !strtotime($report)) {
            $errors['report_date'] = 'Enter a valid report date or leave it blank.';
        }

        $status = (string)($_POST['status'] ?? 'planned');
        if (!in_array($status, ['planned', 'active', 'closed'], true)) {
            $status = 'planned';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/terms', ['year_id' => $yearId], $errors);
        }

        $data = [
            'academic_year_id' => $yearId,
            'name'             => $name,
            'term_number'      => $number,
            'start_date'       => $start,
            'end_date'         => $end,
            'report_date'      => $report !== '' ? $report : null,
            'status'           => $status,
        ];

        if ($existing) {
            Term::updateRow($id, $data);
            audit('administration', 'update', 'terms', $id, 'Updated term ' . $name, $existing, $data);
            $this->respond(true, $name . ' updated.', 'setup/terms', ['year_id' => $yearId]);
        }

        $newId = Term::insert($data);
        audit('administration', 'create', 'terms', $newId, 'Created term ' . $name, null, $data);
        $this->respond(true, $name . ' created.', 'setup/terms', ['year_id' => $yearId]);
    }

    public function activateTerm(): void
    {
        $id   = (int)($_POST['id'] ?? 0);
        $term = $id > 0 ? Term::find($id) : null;
        if (!$term) {
            $this->respond(false, 'That term no longer exists.', 'setup/terms');
        }

        Term::activate($id);
        audit('administration', 'activate', 'terms', $id, 'Set ' . $term['name'] . ' as the active term');

        $this->respond(true, $term['name'] . ' is now the active term.', 'setup/terms', ['year_id' => (int)$term['academic_year_id']]);
    }

    // ------------------------------------------------------------------
    // Classes and streams
    // ------------------------------------------------------------------
    public function classes(): void
    {
        $schoolId = school_id();
        $classId  = (int)($_GET['class_id'] ?? 0);
        $classes  = SchoolClass::forSchool($schoolId);
        $current  = null;

        foreach ($classes as $class) {
            if ((int)$class['id'] === $classId) {
                $current = $class;
                break;
            }
        }

        $editId    = (int)($_GET['edit'] ?? 0);
        $edit      = $editId > 0 ? SchoolClass::find($editId) : null;
        $streamId  = (int)($_GET['edit_stream'] ?? 0);
        $editStream = $streamId > 0 ? Stream::find($streamId) : null;

        $this->render('setup/classes', [
            'pageTitle'  => 'Classes & streams',
            'activeNav'  => 'setup_classes',
            'classes'    => $classes,
            'current'    => $current,
            'streams'    => $current ? Stream::forClass((int)$current['id']) : [],
            'edit'       => $edit,
            'editStream' => $editStream,
            'staff'      => Staff::forSchool($schoolId),
            'canEdit'    => Auth::can('administration', 'edit'),
        ]);
    }

    public function saveClass(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? SchoolClass::find($id) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That class no longer exists.', 'setup/classes');
        }

        $schoolId = school_id();
        $errors   = [];

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 30) {
            $errors['name'] = 'Enter the class name (up to 30 characters), e.g. S1.';
        } elseif (SchoolClass::nameTaken($schoolId, $name, $id ?: null)) {
            $errors['name'] = 'A class with this name already exists.';
        }

        $level = (string)($_POST['level'] ?? 'O-Level');
        if (!in_array($level, ['O-Level', 'A-Level'], true)) {
            $errors['level'] = 'Choose O-Level or A-Level.';
        }

        $order = (int)($_POST['order_index'] ?? 0);

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/classes', [], $errors);
        }

        $data = [
            'school_id'  => $schoolId,
            'name'       => $name,
            'level'      => $level,
            'order_index'=> $order,
            'is_active'  => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($existing) {
            SchoolClass::updateRow($id, $data);
            audit('administration', 'update', 'classes', $id, 'Updated class ' . $name, $existing, $data);
            $this->respond(true, 'Class ' . $name . ' updated.', 'setup/classes');
        }

        $newId = SchoolClass::insert($data);
        audit('administration', 'create', 'classes', $newId, 'Created class ' . $name, null, $data);
        $this->respond(true, 'Class ' . $name . ' created.', 'setup/classes');
    }

    public function toggleClass(): void
    {
        $id    = (int)($_POST['id'] ?? 0);
        $class = $id > 0 ? SchoolClass::find($id) : null;
        if (!$class) {
            $this->respond(false, 'That class no longer exists.', 'setup/classes');
        }

        $new  = (int)$class['is_active'] ? 0 : 1;
        SchoolClass::updateRow($id, ['is_active' => $new]);
        audit('administration', $new ? 'activate' : 'deactivate', 'classes', $id, ($new ? 'Activated ' : 'Deactivated ') . $class['name']);

        $this->respond(true, $class['name'] . ($new ? ' activated.' : ' deactivated.'), 'setup/classes');
    }

    public function saveStream(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? Stream::find($id) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That stream no longer exists.', 'setup/classes');
        }

        $errors  = [];
        $classId = (int)($_POST['class_id'] ?? 0);
        $class   = $classId > 0 ? SchoolClass::find($classId) : null;
        if (!$class || (int)$class['school_id'] !== school_id()) {
            $errors['class_id'] = 'Choose a valid class.';
        }

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 40) {
            $errors['name'] = 'Enter the stream name (up to 40 characters), e.g. East.';
        } elseif ($class && Stream::nameTaken($classId, $name, $id ?: null)) {
            $errors['name'] = 'This stream already exists in the class.';
        }

        $capacity = (int)($_POST['capacity'] ?? 0);
        if ($capacity < 1 || $capacity > 500) {
            $errors['capacity'] = 'Enter a capacity between 1 and 500.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/classes', ['class_id' => $classId], $errors);
        }

        $teacherId = (int)($_POST['class_teacher_id'] ?? 0);
        $data = [
            'class_id'         => $classId,
            'name'             => $name,
            'capacity'         => $capacity,
            'room_name'        => trim((string)($_POST['room_name'] ?? '')) ?: null,
            'class_teacher_id' => $teacherId > 0 ? $teacherId : null,
            'is_active'        => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($existing) {
            Stream::updateRow($id, $data);
            audit('administration', 'update', 'streams', $id, 'Updated stream ' . $name, $existing, $data);
            $this->respond(true, 'Stream ' . $name . ' updated.', 'setup/classes', ['class_id' => $classId]);
        }

        $newId = Stream::insert($data);
        audit('administration', 'create', 'streams', $newId, 'Created stream ' . $name . ' in ' . $class['name'], null, $data);
        $this->respond(true, 'Stream ' . $name . ' created.', 'setup/classes', ['class_id' => $classId]);
    }

    public function toggleStream(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $stream = $id > 0 ? Stream::find($id) : null;
        if (!$stream) {
            $this->respond(false, 'That stream no longer exists.', 'setup/classes');
        }

        $new = (int)$stream['is_active'] ? 0 : 1;
        Stream::updateRow($id, ['is_active' => $new]);
        audit('administration', $new ? 'activate' : 'deactivate', 'streams', $id, ($new ? 'Activated ' : 'Deactivated ') . $stream['name']);

        $this->respond(true, $stream['name'] . ($new ? ' activated.' : ' deactivated.'), 'setup/classes', ['class_id' => (int)$stream['class_id']]);
    }

    // ------------------------------------------------------------------
    // Departments
    // ------------------------------------------------------------------
    public function departments(): void
    {
        $schoolId = school_id();
        $editId   = (int)($_GET['edit'] ?? 0);

        $this->render('setup/departments', [
            'pageTitle'  => 'Departments',
            'activeNav'  => 'setup_departments',
            'departments'=> Department::forSchool($schoolId),
            'edit'       => $editId > 0 ? Department::find($editId) : null,
            'staff'      => Staff::forSchool($schoolId),
            'canEdit'    => Auth::can('administration', 'edit'),
        ]);
    }

    public function saveDepartment(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? Department::find($id) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That department no longer exists.', 'setup/departments');
        }

        $schoolId = school_id();
        $errors   = [];

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $errors['name'] = 'Enter the department name (up to 100 characters).';
        } elseif (Department::nameTaken($schoolId, $name, $id ?: null)) {
            $errors['name'] = 'A department with this name already exists.';
        }

        $type = (string)($_POST['type'] ?? 'academic');
        if (!in_array($type, ['academic', 'non-academic'], true)) {
            $errors['type'] = 'Choose academic or non-academic.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/departments', [], $errors);
        }

        $headId = (int)($_POST['head_staff_id'] ?? 0);
        $data = [
            'school_id'     => $schoolId,
            'name'          => $name,
            'type'          => $type,
            'description'   => trim((string)($_POST['description'] ?? '')) ?: null,
            'head_staff_id' => $headId > 0 ? $headId : null,
            'is_active'     => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($existing) {
            Department::updateRow($id, $data);
            audit('administration', 'update', 'departments', $id, 'Updated department ' . $name, $existing, $data);
            $this->respond(true, 'Department ' . $name . ' updated.', 'setup/departments');
        }

        $newId = Department::insert($data);
        audit('administration', 'create', 'departments', $newId, 'Created department ' . $name, null, $data);
        $this->respond(true, 'Department ' . $name . ' created.', 'setup/departments');
    }

    public function toggleDepartment(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        $department = $id > 0 ? Department::find($id) : null;
        if (!$department) {
            $this->respond(false, 'That department no longer exists.', 'setup/departments');
        }

        $new = (int)$department['is_active'] ? 0 : 1;
        Department::updateRow($id, ['is_active' => $new]);
        audit('administration', $new ? 'activate' : 'deactivate', 'departments', $id, ($new ? 'Activated ' : 'Deactivated ') . $department['name']);

        $this->respond(true, $department['name'] . ($new ? ' activated.' : ' deactivated.'), 'setup/departments');
    }

    // ------------------------------------------------------------------
    // Houses
    // ------------------------------------------------------------------
    public function houses(): void
    {
        $schoolId = school_id();
        $editId   = (int)($_GET['edit'] ?? 0);

        $this->render('setup/houses', [
            'pageTitle' => 'Houses',
            'activeNav' => 'setup_houses',
            'houses'    => House::forSchool($schoolId),
            'edit'      => $editId > 0 ? House::find($editId) : null,
            'staff'     => Staff::forSchool($schoolId),
            'canEdit'   => Auth::can('administration', 'edit'),
        ]);
    }

    public function saveHouse(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? House::find($id) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That house no longer exists.', 'setup/houses');
        }

        $schoolId = school_id();
        $errors   = [];

        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            $errors['name'] = 'Enter the house name (up to 60 characters).';
        } elseif (House::nameTaken($schoolId, $name, $id ?: null)) {
            $errors['name'] = 'A house with this name already exists.';
        }

        $colour = trim((string)($_POST['colour'] ?? ''));
        if ($colour !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colour)) {
            $errors['colour'] = 'Choose a valid colour, e.g. #ef4444.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'setup/houses', [], $errors);
        }

        $masterId = (int)($_POST['house_master_staff_id'] ?? 0);
        $data = [
            'school_id'           => $schoolId,
            'name'                => $name,
            'colour'              => $colour ?: null,
            'motto'               => trim((string)($_POST['motto'] ?? '')) ?: null,
            'house_master_staff_id' => $masterId > 0 ? $masterId : null,
            'is_active'           => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($existing) {
            House::updateRow($id, $data);
            audit('administration', 'update', 'houses', $id, 'Updated house ' . $name, $existing, $data);
            $this->respond(true, 'House ' . $name . ' updated.', 'setup/houses');
        }

        $newId = House::insert($data);
        audit('administration', 'create', 'houses', $newId, 'Created house ' . $name, null, $data);
        $this->respond(true, 'House ' . $name . ' created.', 'setup/houses');
    }

    public function toggleHouse(): void
    {
        $id    = (int)($_POST['id'] ?? 0);
        $house = $id > 0 ? House::find($id) : null;
        if (!$house) {
            $this->respond(false, 'That house no longer exists.', 'setup/houses');
        }

        $new = (int)$house['is_active'] ? 0 : 1;
        House::updateRow($id, ['is_active' => $new]);
        audit('administration', $new ? 'activate' : 'deactivate', 'houses', $id, ($new ? 'Activated ' : 'Deactivated ') . $house['name']);

        $this->respond(true, $house['name'] . ($new ? ' activated.' : ' deactivated.'), 'setup/houses');
    }

    // ------------------------------------------------------------------
    // Audit logs
    // ------------------------------------------------------------------
    public function audit(): void
    {
        $module = trim((string)($_GET['module'] ?? ''));
        $user   = trim((string)($_GET['user'] ?? ''));
        $from   = trim((string)($_GET['from'] ?? ''));
        $to     = trim((string)($_GET['to'] ?? ''));

        $sql    = 'SELECT * FROM audit_logs WHERE 1 = 1';
        $params = [];

        if ($module !== '') {
            $sql .= ' AND module = ?';
            $params[] = $module;
        }
        if ($user !== '') {
            $sql .= ' AND user_name LIKE ?';
            $params[] = '%' . $user . '%';
        }
        if ($from !== '' && strtotime($from)) {
            $sql .= ' AND created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '' && strtotime($to)) {
            $sql .= ' AND created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $sql .= ' ORDER BY id DESC LIMIT 500';

        $modules = AuditLog::query('SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module');

        $this->render('setup/audit', [
            'pageTitle' => 'Audit logs',
            'activeNav' => 'setup_audit',
            'logs'      => AuditLog::query($sql, $params),
            'modules'   => $modules,
            'filters'   => ['module' => $module, 'user' => $user, 'from' => $from, 'to' => $to],
        ]);
    }
}
