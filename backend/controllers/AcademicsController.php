<?php
declare(strict_types=1);

final class AcademicsController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $years = AcademicManagement::years($schoolId);
        $activeYear = AcademicYear::active($schoolId);
        $yearId = (int)($_GET['year_id'] ?? ($activeYear['id'] ?? ($years[0]['id'] ?? 0)));
        $year = $yearId > 0 ? AcademicManagement::year($yearId, $schoolId) : null;
        if (!$year) {
            $yearId = 0;
        }

        $terms = $yearId > 0 ? AcademicManagement::terms($schoolId, $yearId) : [];
        $curriculumEditId = (int)($_GET['curriculum_id'] ?? 0);
        $subjectEditId = (int)($_GET['subject_id'] ?? 0);
        $schemeEditId = (int)($_GET['scheme_id'] ?? 0);
        $curricula = AcademicManagement::curricula($schoolId);
        $subjects = AcademicManagement::subjects($schoolId);
        $classes = AcademicManagement::classes($schoolId);
        $streams = AcademicManagement::streams($schoolId);
        $staff = AcademicManagement::staff($schoolId);

        $timetableId = (int)($_GET['timetable_id'] ?? 0);
        $timetables = $yearId > 0 ? AcademicManagement::timetables($schoolId, $yearId) : [];
        $timetable = null;
        if ($timetableId > 0) {
            foreach ($timetables as $item) {
                if ((int)$item['id'] === $timetableId) {
                    $timetable = $item;
                    break;
                }
            }
        }
        if (!$timetable && $timetables) {
            $timetable = $timetables[0];
            $timetableId = (int)$timetable['id'];
        }
        $timetableSlots = $timetable ? AcademicManagement::timetableSlots($timetableId) : [];

        $homeworkClassId = (int)($_GET['homework_class_id'] ?? 0);
        if ($homeworkClassId > 0 && !AcademicManagement::one(
            'SELECT id FROM classes WHERE id = ? AND school_id = ? AND is_active = 1',
            [$homeworkClassId, $schoolId]
        )) {
            $homeworkClassId = 0;
        }
        $homeworkList = $yearId > 0
            ? AcademicManagement::homeworkList($schoolId, $yearId, $homeworkClassId > 0 ? $homeworkClassId : null)
            : [];
        $homeworkId = (int)($_GET['homework_id'] ?? 0);
        $homework = $homeworkId > 0 ? AcademicManagement::homework($homeworkId, $schoolId) : null;
        if ($homework && (int)$homework['academic_year_id'] !== $yearId) {
            $homework = null;
            $homeworkId = 0;
        }
        $homeworkRoster = $homework ? AcademicManagement::homeworkRoster($homework, $schoolId) : [];

        $events = AcademicManagement::upcomingEvents($schoolId, date('Y-m-d'));

        $syllabi = AcademicManagement::syllabi($schoolId);
        $syllabusId = (int)($_GET['syllabus_id'] ?? 0);
        $syllabus = null;
        foreach ($syllabi as $item) {
            if ((int)$item['id'] === $syllabusId) {
                $syllabus = $item;
                break;
            }
        }
        if (!$syllabus) {
            $syllabusId = 0;
        }
        $syllabusTopics = $syllabus ? AcademicManagement::syllabusTopics($syllabusId) : [];

        $coverageSubjectId = (int)($_GET['coverage_subject_id'] ?? 0);
        $coverageClassId = (int)($_GET['coverage_class_id'] ?? 0);
        $coverageStreamId = (int)($_GET['coverage_stream_id'] ?? 0);
        $coverageTermId = (int)($_GET['coverage_term_id'] ?? 0);
        if ($coverageSubjectId > 0 && !AcademicManagement::subject($coverageSubjectId, $schoolId)) {
            $coverageSubjectId = 0;
        }
        if ($coverageClassId > 0 && !AcademicManagement::one(
            'SELECT id FROM classes WHERE id = ? AND school_id = ? AND is_active = 1',
            [$coverageClassId, $schoolId]
        )) {
            $coverageClassId = 0;
        }
        if ($coverageStreamId > 0 && !$this->validStream($coverageStreamId, $coverageClassId, $schoolId)) {
            $coverageStreamId = 0;
        }
        $validTermIds = [];
        foreach ($terms as $term) {
            $validTermIds[] = (int)$term['id'];
        }
        if ($coverageTermId > 0 && !in_array($coverageTermId, $validTermIds, true)) {
            $coverageTermId = 0;
        }
        $coverageSyllabus = $coverageSubjectId > 0
            ? AcademicManagement::currentSyllabus($schoolId, $coverageSubjectId) : null;
        $coverageTopics = $coverageSyllabus && $coverageClassId > 0 && $coverageTermId > 0
            ? AcademicManagement::syllabusTopics((int)$coverageSyllabus['id']) : [];
        $coverageByTopic = [];
        if ($coverageTopics) {
            foreach (AcademicManagement::coverageRows(
                $coverageSubjectId,
                $coverageClassId,
                $coverageStreamId > 0 ? $coverageStreamId : null,
                $coverageTermId
            ) as $row) {
                $coverageByTopic[(int)$row['topic_id']] = $row;
            }
        }
        $coveragePct = 0;
        if ($coverageTopics) {
            $covered = 0;
            foreach ($coverageTopics as $topic) {
                if (isset($coverageByTopic[(int)$topic['id']])) {
                    $covered++;
                }
            }
            $coveragePct = (int)round($covered * 100 / count($coverageTopics));
        }

        $workload = $yearId > 0 ? AcademicManagement::workload($schoolId, $yearId) : [];
        $activities = $yearId > 0 ? AcademicManagement::activities($schoolId, $yearId) : [];

        $this->render('academics/index', [
            'pageTitle' => 'Academic management',
            'activeNav' => 'academics',
            'schoolId' => $schoolId,
            'year' => $year,
            'yearId' => $yearId,
            'years' => $years,
            'terms' => $terms,
            'curricula' => $curricula,
            'curriculumEdit' => $curriculumEditId > 0
                ? AcademicManagement::curriculum($curriculumEditId, $schoolId) : null,
            'subjects' => $subjects,
            'subjectEdit' => $subjectEditId > 0
                ? AcademicManagement::subject($subjectEditId, $schoolId) : null,
            'departments' => AcademicManagement::departments($schoolId),
            'classes' => $classes,
            'streams' => $streams,
            'staff' => $staff,
            'subjectAllocations' => $yearId > 0 ? AcademicManagement::subjectAllocations($schoolId, $yearId) : [],
            'teacherAllocations' => $yearId > 0 ? AcademicManagement::teacherAllocations($schoolId, $yearId) : [],
            'schemes' => $yearId > 0 ? AcademicManagement::schemes($schoolId, $yearId) : [],
            'schemeEdit' => $schemeEditId > 0
                ? AcademicManagement::scheme($schemeEditId, $schoolId) : null,
            'rooms' => AcademicManagement::rooms($schoolId),
            'timetables' => $timetables,
            'timetable' => $timetable,
            'timetableId' => $timetableId,
            'timetableSlots' => $timetableSlots,
            'homeworkList' => $homeworkList,
            'homeworkClassId' => $homeworkClassId,
            'homework' => $homework,
            'homeworkId' => $homeworkId,
            'homeworkRoster' => $homeworkRoster,
            'events' => $events,
            'syllabi' => $syllabi,
            'syllabus' => $syllabus,
            'syllabusId' => $syllabusId,
            'syllabusTopics' => $syllabusTopics,
            'coverageSubjectId' => $coverageSubjectId,
            'coverageClassId' => $coverageClassId,
            'coverageStreamId' => $coverageStreamId,
            'coverageTermId' => $coverageTermId,
            'coverageTopics' => $coverageTopics,
            'coverageByTopic' => $coverageByTopic,
            'coveragePct' => $coveragePct,
            'coverageSyllabus' => $coverageSyllabus,
            'workload' => $workload,
            'activities' => $activities,
        ]);
    }

    public function saveCurriculum(): void
    {
        $schoolId = school_id();
        $id = $this->optionalId($_POST['id'] ?? null);
        $old = $id ? AcademicManagement::curriculum($id, $schoolId) : null;
        if ($id && !$old) {
            $this->respond(false, 'Curriculum not found.', 'academics');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $scope = (string)($_POST['level_scope'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');
        $description = trim((string)($_POST['description'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120 || !$this->validCode($code, 30)
            || !in_array($scope, ['O-Level', 'A-Level', 'Both'], true)
            || !in_array($status, ['active', 'draft', 'archived'], true)
            || mb_strlen($description) > 65535) {
            $this->respond(false, 'Enter a valid curriculum name, code, level, status, and description.', 'academics');
        }
        if (AcademicManagement::curriculumCodeTaken($schoolId, $code, $id)) {
            $this->respond(false, 'That curriculum code is already in use at this school.', 'academics');
        }

        $data = ['name' => $name, 'code' => $code, 'level_scope' => $scope,
            'description' => $description !== '' ? $description : null, 'status' => $status];
        $savedId = AcademicManagement::saveCurriculum($schoolId, $data, $id);
        audit('academics', $id ? 'update_curriculum' : 'create_curriculum', 'curricula', $savedId,
            'Saved curriculum ' . $code, $old, $data);
        $this->respond(true, 'Curriculum saved.', 'academics');
    }

    public function saveSubject(): void
    {
        $schoolId = school_id();
        $id = $this->optionalId($_POST['id'] ?? null);
        $old = $id ? AcademicManagement::subject($id, $schoolId) : null;
        if ($id && !$old) {
            $this->respond(false, 'Subject not found.', 'academics');
        }
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $shortName = trim((string)($_POST['short_name'] ?? ''));
        $level = (string)($_POST['level'] ?? '');
        $category = (string)($_POST['category'] ?? '');
        $departmentId = $this->optionalId($_POST['department_id'] ?? null);
        $unebCode = strtoupper(trim((string)($_POST['uneb_subject_code'] ?? '')));
        $creditRaw = trim((string)($_POST['credit_points'] ?? ''));
        $order = filter_var($_POST['order_index'] ?? '0', FILTER_VALIDATE_INT);

        $credit = null;
        if ($creditRaw !== '') {
            if (!is_numeric($creditRaw) || (float)$creditRaw < 0 || (float)$creditRaw > 999.9
                || !preg_match('/^\d{1,3}(?:\.\d)?$/', $creditRaw)) {
                $this->respond(false, 'Credit points must be a non-negative number with at most one decimal place.', 'academics');
            }
            $credit = number_format((float)$creditRaw, 1, '.', '');
        }

        if (!$this->validCode($code, 20) || $name === '' || mb_strlen($name) > 120
            || mb_strlen($shortName) > 30 || mb_strlen($unebCode) > 20
            || !in_array($level, ['O-Level', 'A-Level', 'Both'], true)
            || !in_array($category, ['compulsory', 'elective', 'principal', 'subsidiary'], true)
            || $order === false || $order < 0 || $order > 32767) {
            $this->respond(false, 'Enter a valid subject code, name, level, category, and order.', 'academics');
        }
        if ($departmentId !== null
            && !AcademicManagement::one('SELECT id FROM departments WHERE id = ? AND school_id = ? AND is_active = 1', [$departmentId, $schoolId])) {
            $this->respond(false, 'Choose an active department from this school.', 'academics');
        }
        if (AcademicManagement::subjectCodeTaken($schoolId, $code, $id)) {
            $this->respond(false, 'That subject code is already in use at this school.', 'academics');
        }

        $data = [
            'code' => $code, 'name' => $name,
            'short_name' => $shortName !== '' ? $shortName : null,
            'level' => $level, 'category' => $category, 'department_id' => $departmentId,
            'uneb_subject_code' => $unebCode !== '' ? $unebCode : null,
            'credit_points' => $credit, 'is_composite' => isset($_POST['is_composite']) ? 1 : 0,
            'order_index' => $order, 'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        $savedId = AcademicManagement::saveSubject($schoolId, $data, $id);
        audit('academics', $id ? 'update_subject' : 'create_subject', 'subjects', $savedId,
            'Saved subject ' . $code, $old, $data);
        $this->respond(true, 'Subject saved.', 'academics');
    }

    public function saveSubjectAllocation(): void
    {
        $schoolId = school_id();
        [$subjectId, $classId, $streamId, $yearId, $termId] = $this->allocationInput($schoolId);
        if (!AcademicManagement::subject($subjectId, $schoolId)
            || !$this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            || !$this->validStream($streamId, $classId, $schoolId)
            || !$this->validYearTerm($yearId, $termId, $schoolId)) {
            $this->respond(false, 'Choose a subject, class, stream, year, and term belonging to this school.', 'academics', ['year_id' => $yearId]);
        }

        $compulsory = isset($_POST['is_compulsory']);
        $allocationId = AcademicManagement::saveSubjectAllocation(
            $subjectId, $classId, $streamId, $yearId, $termId, $compulsory,
            (int)(Auth::user()['id'] ?? 0)
        );
        audit('academics', 'allocate_subject', 'subject_allocations', $allocationId,
            'Allocated subject ' . $subjectId . ' to class ' . $classId,
            null, ['subject_id' => $subjectId, 'class_id' => $classId, 'stream_id' => $streamId,
                'academic_year_id' => $yearId, 'term_id' => $termId, 'is_compulsory' => $compulsory]);
        $this->respond(true, 'Subject allocation saved.', 'academics', ['year_id' => $yearId]);
    }

    public function deactivateSubjectAllocation(): void
    {
        $schoolId = school_id();
        $id = $this->optionalId($_POST['id'] ?? null);
        $row = $id ? AcademicManagement::subjectAllocation($id, $schoolId) : null;
        if (!$row) {
            $this->respond(false, 'Subject allocation not found.', 'academics');
        }
        AcademicManagement::execute('UPDATE subject_allocations SET is_active = 0 WHERE id = ?', [$id]);
        audit('academics', 'deactivate_subject_allocation', 'subject_allocations', $id,
            'Deactivated subject allocation', $row, ['is_active' => 0]);
        $this->respond(true, 'Subject allocation deactivated.', 'academics', ['year_id' => (int)$row['academic_year_id']]);
    }

    public function saveTeacherAllocation(): void
    {
        $schoolId = school_id();
        [$subjectId, $classId, $streamId, $yearId, $termId] = $this->allocationInput($schoolId);
        $staffId = $this->positiveId($_POST['staff_id'] ?? null);
        $lessons = filter_var($_POST['lessons_per_week'] ?? null, FILTER_VALIDATE_INT);
        $staffValid = $staffId > 0 && (bool)AcademicManagement::one(
            "SELECT id FROM staff WHERE id = ? AND school_id = ? AND employment_status IN ('active','probation','contract')",
            [$staffId, $schoolId]
        );
        if (!$staffValid || !AcademicManagement::subject($subjectId, $schoolId)
            || !$this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            || !$this->validStream($streamId, $classId, $schoolId)
            || !$this->validYearTerm($yearId, $termId, $schoolId)
            || !$this->subjectOffered($subjectId, $classId, $streamId, $yearId, $termId)
            || $lessons === false || $lessons < 1 || $lessons > 60) {
            $this->respond(false, 'Choose valid school staff and an offered subject/class allocation; lessons per week must be 1–60.', 'academics', ['year_id' => $yearId]);
        }

        $allocationId = AcademicManagement::saveTeacherAllocation(
            $staffId, $subjectId, $classId, $streamId, $yearId, $termId, $lessons,
            isset($_POST['is_primary']), (int)(Auth::user()['id'] ?? 0)
        );
        audit('academics', 'allocate_teacher', 'teacher_allocations', $allocationId,
            'Allocated staff ' . $staffId . ' to subject ' . $subjectId . ' in class ' . $classId,
            null, ['staff_id' => $staffId, 'subject_id' => $subjectId, 'class_id' => $classId,
                'stream_id' => $streamId, 'academic_year_id' => $yearId, 'term_id' => $termId,
                'lessons_per_week' => $lessons, 'is_primary' => isset($_POST['is_primary'])]);
        $this->respond(true, 'Teacher allocation saved.', 'academics', ['year_id' => $yearId]);
    }

    public function removeTeacherAllocation(): void
    {
        $schoolId = school_id();
        $id = $this->optionalId($_POST['id'] ?? null);
        $row = $id ? AcademicManagement::teacherAllocation($id, $schoolId) : null;
        if (!$row) {
            $this->respond(false, 'Teacher allocation not found.', 'academics');
        }
        AcademicManagement::removeTeacherAllocation($id, $schoolId);
        audit('academics', 'remove_teacher_allocation', 'teacher_allocations', $id,
            'Removed teacher allocation', $row);
        $this->respond(true, 'Teacher allocation removed.', 'academics', ['year_id' => (int)$row['academic_year_id']]);
    }

    public function saveScheme(): void
    {
        $schoolId = school_id();
        $id = $this->optionalId($_POST['id'] ?? null);
        $old = $id ? AcademicManagement::scheme($id, $schoolId) : null;
        if ($id && !$old) {
            $this->respond(false, 'Scheme of work not found.', 'academics');
        }
        if ($old && !in_array($old['status'], ['draft', 'returned'], true)) {
            $this->respond(false, 'Only draft or returned schemes can be changed.', 'academics', ['year_id' => (int)$old['academic_year_id']]);
        }

        $staffId = $this->positiveId($_POST['staff_id'] ?? null);
        $subjectId = $this->positiveId($_POST['subject_id'] ?? null);
        $classId = $this->positiveId($_POST['class_id'] ?? null);
        $streamId = $this->optionalId($_POST['stream_id'] ?? null);
        $yearId = $this->positiveId($_POST['academic_year_id'] ?? null);
        $termId = $this->positiveId($_POST['term_id'] ?? null);
        $week = filter_var($_POST['week_number'] ?? null, FILTER_VALIDATE_INT);
        $intent = (string)($_POST['intent'] ?? 'draft');
        $date = trim((string)($_POST['week_starting'] ?? ''));
        $topic = trim((string)($_POST['topic'] ?? ''));

        $staffValid = $staffId > 0 && (bool)AcademicManagement::one(
            "SELECT id FROM staff WHERE id = ? AND school_id = ? AND employment_status IN ('active','probation','contract')",
            [$staffId, $schoolId]
        );
        $valid = $staffValid
            && AcademicManagement::subject($subjectId, $schoolId)
            && $this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            && $this->validStream($streamId, $classId, $schoolId)
            && $this->validYearTerm($yearId, $termId, $schoolId)
            && $this->subjectOffered($subjectId, $classId, $streamId, $yearId, $termId)
            && $this->teacherAssigned($staffId, $subjectId, $classId, $streamId, $yearId, $termId)
            && $week !== false && $week >= 1 && $week <= 53
            && in_array($intent, ['draft', 'submit'], true)
            && $topic !== '' && mb_strlen($topic) <= 180
            && $this->optionalDate($date);

        $textLimits = [
            'sub_topics' => 65535, 'objectives' => 65535, 'learning_experiences' => 65535,
            'teaching_methods' => 255, 'learning_materials' => 255, 'assessment' => 65535, 'remarks' => 255,
        ];
        $fields = [];
        foreach ($textLimits as $field => $limit) {
            $value = trim((string)($_POST[$field] ?? ''));
            if (mb_strlen($value) > $limit) {
                $valid = false;
            }
            $fields[$field] = $value !== '' ? $value : null;
        }
        if (!$valid) {
            $this->respond(false, 'Complete the scheme fields using a valid school staff member and allocated subject, class, year, and term.', 'academics', ['year_id' => $yearId]);
        }
        if (AcademicManagement::schemeKeyExists($staffId, $subjectId, $classId, $termId, $week, $id)) {
            $this->respond(false, 'A scheme already exists for this teacher, subject, class, term, and week.', 'academics', ['year_id' => $yearId]);
        }

        $data = [
            'staff_id' => $staffId, 'subject_id' => $subjectId, 'class_id' => $classId,
            'stream_id' => $streamId, 'academic_year_id' => $yearId, 'term_id' => $termId,
            'week_number' => $week, 'week_starting' => $date !== '' ? $date : null,
            'topic' => $topic, 'status' => $intent === 'submit' ? 'submitted' : 'draft',
            'submitted_at' => $intent === 'submit' ? date('Y-m-d H:i:s') : null,
        ] + $fields;
        $savedId = AcademicManagement::saveScheme($data, $id, (int)(Auth::user()['id'] ?? 0));
        audit('academics', $id ? 'update_scheme' : 'create_scheme', 'schemes_of_work', $savedId,
            $intent === 'submit' ? 'Submitted scheme of work for review' : 'Saved scheme of work draft',
            $old, $data);
        $this->respond(true, $intent === 'submit' ? 'Scheme submitted for review.' : 'Scheme draft saved.', 'academics', ['year_id' => $yearId]);
    }

    public function saveTimetable(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $name = trim((string)($_POST['name'] ?? ''));
        $classId = $this->positiveId($_POST['class_id'] ?? null);
        $streamId = $this->optionalId($_POST['stream_id'] ?? null);
        $yearId = $this->positiveId($_POST['academic_year_id'] ?? null);
        $termId = $this->positiveId($_POST['term_id'] ?? null);
        $status = (string)($_POST['status'] ?? 'draft');
        $params = ['year_id' => $yearId];

        if ($name === '' || mb_strlen($name) > 120
            || !$this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            || !$this->validStream($streamId, $classId, $schoolId)
            || !$this->validYearTerm($yearId, $termId, $schoolId)
            || !in_array($status, ['draft', 'published'], true)) {
            $this->respond(false, 'Enter a timetable name and a valid class, stream, year, term, and status.', 'academics', $params,
                ['name' => 'A timetable name and valid class, year, and term are required.']);
        }
        if (AcademicManagement::timetableKeyExists($schoolId, $classId, $streamId, $yearId, $termId)) {
            $this->respond(false, 'A timetable already exists for this class, stream, year, and term.', 'academics', $params,
                ['name' => 'A timetable already exists for this class and term.']);
        }

        $data = [
            'name' => $name, 'scope' => 'class', 'class_id' => $classId, 'stream_id' => $streamId,
            'academic_year_id' => $yearId, 'term_id' => $termId, 'status' => $status,
        ];
        $id = AcademicManagement::saveTimetable($schoolId, $data, (int)(Auth::user()['id'] ?? 0));
        audit('academics', 'create_timetable', 'timetables', $id, 'Created timetable ' . $name, null, $data);
        $this->respond(true, 'Timetable created. Add lesson slots to build the weekly grid.', 'academics',
            ['year_id' => $yearId, 'timetable_id' => $id]);
    }

    public function deleteTimetable(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $id = $this->positiveId($_POST['id'] ?? null);
        $row = $id > 0 ? AcademicManagement::timetable($id, $schoolId) : null;
        if (!$row) {
            $this->respond(false, 'Timetable not found.', 'academics');
        }
        AcademicManagement::removeTimetable($id, $schoolId);
        audit('academics', 'delete_timetable', 'timetables', $id, 'Deleted timetable ' . $row['name'], $row);
        $this->respond(true, 'Timetable and its slots deleted.', 'academics', ['year_id' => (int)$row['academic_year_id']]);
    }

    public function saveTimetableSlot(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $timetableId = $this->positiveId($_POST['timetable_id'] ?? null);
        $timetable = $timetableId > 0 ? AcademicManagement::timetable($timetableId, $schoolId) : null;
        if (!$timetable) {
            $this->respond(false, 'Choose a timetable before adding a slot.', 'academics', [], ['timetable_id' => 'Timetable not found.']);
        }
        $yearId = (int)$timetable['academic_year_id'];
        $termId = (int)$timetable['term_id'];
        $params = ['year_id' => $yearId, 'timetable_id' => $timetableId];
        if ($timetable['status'] === 'archived') {
            $this->respond(false, 'Archived timetables cannot be changed.', 'academics', $params);
        }

        $day = (string)($_POST['day_of_week'] ?? '');
        $period = filter_var($_POST['period_number'] ?? null, FILTER_VALIDATE_INT);
        $start = trim((string)($_POST['start_time'] ?? ''));
        $end = trim((string)($_POST['end_time'] ?? ''));
        $subjectId = $this->positiveId($_POST['subject_id'] ?? null);
        $staffId = $this->positiveId($_POST['staff_id'] ?? null);
        $roomId = $this->optionalId($_POST['room_id'] ?? null);
        $notes = trim((string)($_POST['notes'] ?? ''));
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $staffValid = $staffId > 0 && (bool)AcademicManagement::one(
            "SELECT id FROM staff WHERE id = ? AND school_id = ? AND employment_status IN ('active','probation','contract')",
            [$staffId, $schoolId]
        );
        $roomValid = $roomId === null || (bool)AcademicManagement::one(
            'SELECT id FROM rooms WHERE id = ? AND school_id = ? AND is_active = 1',
            [$roomId, $schoolId]
        );
        $valid = in_array($day, $days, true)
            && $period !== false && $period >= 1 && $period <= 30
            && $this->validTime($start) && $this->validTime($end)
            && strtotime($end) > strtotime($start)
            && AcademicManagement::subject($subjectId, $schoolId)
            && $staffValid && $roomValid
            && mb_strlen($notes) <= 160;

        if (!$valid) {
            $this->respond(false, 'Enter a valid day, period, time range, subject, and teacher for the slot.', 'academics', $params,
                ['start_time' => 'Start and end times must be valid and the end time must be after the start time.']);
        }

        $teacherClash = AcademicManagement::teacherSlotClash($yearId, $termId, $day, $period, $staffId);
        if ($teacherClash) {
            $this->respond(false, 'That teacher is already booked on ' . $day . ' period ' . $period . '.', 'academics', $params,
                ['staff_id' => 'Teacher clash: already teaching ' . $teacherClash['class_name']
                    . ($teacherClash['stream_name'] ? ' — ' . $teacherClash['stream_name'] : '')
                    . ' at this time.']);
        }
        $classClash = AcademicManagement::classSlotClash($yearId, $termId, $day, $period, (int)$timetable['class_id']);
        if ($classClash) {
            $this->respond(false, 'This class is already booked on ' . $day . ' period ' . $period . '.', 'academics', $params,
                ['period_number' => 'Class clash: ' . $classClash['class_name']
                    . ($classClash['stream_name'] ? ' — ' . $classClash['stream_name'] : '')
                    . ' already has a lesson at this time.']);
        }

        $data = [
            'day_of_week' => $day, 'period_number' => $period, 'start_time' => $start, 'end_time' => $end,
            'subject_id' => $subjectId, 'staff_id' => $staffId, 'class_id' => (int)$timetable['class_id'],
            'stream_id' => $timetable['stream_id'] !== null ? (int)$timetable['stream_id'] : null,
            'room_id' => $roomId, 'lesson_type' => 'lesson', 'notes' => $notes !== '' ? $notes : null,
        ];
        $slotId = AcademicManagement::saveTimetableSlot($timetableId, $data);
        audit('academics', 'create_timetable_slot', 'timetable_slots', $slotId,
            'Added ' . $day . ' period ' . $period . ' slot to timetable ' . $timetable['name'], null, $data);
        $this->respond(true, 'Timetable slot added.', 'academics', $params);
    }

    public function deleteTimetableSlot(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $id = $this->positiveId($_POST['id'] ?? null);
        $slot = $id > 0 ? AcademicManagement::timetableSlot($id, $schoolId) : null;
        if (!$slot) {
            $this->respond(false, 'Timetable slot not found.', 'academics');
        }
        AcademicManagement::removeTimetableSlot($id, $schoolId);
        audit('academics', 'delete_timetable_slot', 'timetable_slots', $id,
            'Removed ' . $slot['day_of_week'] . ' period ' . $slot['period_number'] . ' slot', $slot);
        $this->respond(true, 'Timetable slot removed.', 'academics', [
            'year_id' => (int)$slot['academic_year_id'],
            'timetable_id' => (int)$slot['timetable_id'],
        ]);
    }

    public function saveHomework(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $staffId = $this->positiveId($_POST['staff_id'] ?? null);
        $subjectId = $this->positiveId($_POST['subject_id'] ?? null);
        $classId = $this->positiveId($_POST['class_id'] ?? null);
        $streamId = $this->optionalId($_POST['stream_id'] ?? null);
        $yearId = $this->positiveId($_POST['academic_year_id'] ?? null);
        $termId = $this->positiveId($_POST['term_id'] ?? null);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $assignedDate = trim((string)($_POST['assigned_date'] ?? ''));
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $status = (string)($_POST['status'] ?? 'published');
        $marksRaw = trim((string)($_POST['total_marks'] ?? ''));

        $staffValid = $staffId > 0 && (bool)AcademicManagement::one(
            "SELECT id FROM staff WHERE id = ? AND school_id = ? AND employment_status IN ('active','probation','contract')",
            [$staffId, $schoolId]
        );
        $marks = null;
        $marksValid = true;
        if ($marksRaw !== '') {
            $marksValid = is_numeric($marksRaw) && (float)$marksRaw >= 0 && (float)$marksRaw <= 999999.99
                && preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $marksRaw) === 1;
            if ($marksValid) {
                $marks = number_format((float)$marksRaw, 2, '.', '');
            }
        }

        $valid = $staffValid
            && AcademicManagement::subject($subjectId, $schoolId)
            && $this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            && $this->validStream($streamId, $classId, $schoolId)
            && $this->validYearTerm($yearId, $termId, $schoolId)
            && $title !== '' && mb_strlen($title) <= 180
            && mb_strlen($description) <= 65535
            && $this->requiredDate($assignedDate) && $this->requiredDate($dueDate)
            && strtotime($dueDate) >= strtotime($assignedDate)
            && in_array($status, ['draft', 'published'], true)
            && $marksValid;

        if (!$valid) {
            $this->respond(false, 'Complete the homework form with a valid teacher, subject, class, term, title, and due date on or after the assigned date.', 'academics',
                ['year_id' => $yearId, 'homework_class_id' => $classId],
                ['due_date' => 'The due date must be a valid date on or after the assigned date.']);
        }

        $data = [
            'staff_id' => $staffId, 'subject_id' => $subjectId, 'class_id' => $classId,
            'stream_id' => $streamId, 'academic_year_id' => $yearId, 'term_id' => $termId,
            'title' => $title, 'description' => $description !== '' ? $description : null,
            'assigned_date' => $assignedDate, 'due_date' => $dueDate, 'total_marks' => $marks,
            'status' => $status,
        ];
        $id = AcademicManagement::saveHomework($data);
        audit('academics', 'create_homework', 'homework', $id, 'Assigned homework "' . $title . '"', null, $data);
        $this->respond(true, 'Homework assigned.', 'academics', [
            'year_id' => $yearId, 'homework_class_id' => $classId, 'homework_id' => $id,
        ]);
    }

    public function saveHomeworkSubmissions(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $homeworkId = $this->positiveId($_POST['homework_id'] ?? null);
        $homework = $homeworkId > 0 ? AcademicManagement::homework($homeworkId, $schoolId) : null;
        if (!$homework) {
            $this->respond(false, 'Homework not found.', 'academics');
        }
        $yearId = (int)$homework['academic_year_id'];
        $classId = (int)$homework['class_id'];
        $params = [
            'year_id' => $yearId, 'homework_class_id' => $classId, 'homework_id' => $homeworkId,
        ];

        $posted = $_POST['submitted'] ?? [];
        if (!is_array($posted) || count($posted) > 500) {
            $this->respond(false, 'Submit the class roster to record homework submissions.', 'academics', $params);
        }
        $submittedIds = [];
        foreach ($posted as $value) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            if ($parsed === false || $parsed < 1) {
                $this->respond(false, 'The submitted roster contains an invalid learner.', 'academics', $params);
            }
            $submittedIds[] = (int)$parsed;
        }

        $roster = AcademicManagement::homeworkRoster($homework, $schoolId);
        $rosterIds = [];
        foreach ($roster as $student) {
            $rosterIds[] = (int)$student['id'];
        }
        foreach ($submittedIds as $studentId) {
            if (!in_array($studentId, $rosterIds, true)) {
                $this->respond(false, 'The submitted roster does not match this homework class.', 'academics', $params);
            }
        }

        $count = 0;
        foreach ($rosterIds as $studentId) {
            AcademicManagement::saveHomeworkSubmission(
                $homeworkId, $studentId, in_array($studentId, $submittedIds, true), (string)$homework['due_date']
            );
            $count++;
        }
        audit('academics', 'record_homework_submissions', 'homework_submissions', $homeworkId,
            'Recorded submission status for ' . count($submittedIds) . ' of ' . $count . ' learners', null,
            ['homework_id' => $homeworkId, 'submitted' => $submittedIds]);
        $this->respond(true, 'Submission status saved for ' . $count . ' learners.', 'academics', $params);
    }

    public function saveEvent(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $yearId = $this->positiveId($_POST['academic_year_id'] ?? null);
        $termId = $this->optionalId($_POST['term_id'] ?? null);
        $title = trim((string)($_POST['title'] ?? ''));
        $type = (string)($_POST['event_type'] ?? 'event');
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $types = ['term_start', 'term_end', 'examination', 'holiday', 'event', 'deadline', 'meeting', 'sports', 'visiting_day'];
        $params = ['year_id' => $yearId];

        if ($title === '' || mb_strlen($title) > 160
            || !in_array($type, $types, true)
            || !$this->requiredDate($startDate)
            || !$this->optionalDate($endDate)
            || ($endDate !== '' && strtotime($endDate) < strtotime($startDate))
            || mb_strlen($description) > 65535
            || ($yearId > 0 ? !$this->validYearTerm($yearId, $termId, $schoolId) : $termId !== null)) {
            $this->respond(false, 'Enter a valid event title, type, and start date (with an end date on or after it).', 'academics', $params,
                ['start_date' => 'A valid start date is required and the end date cannot be before it.']);
        }

        $data = [
            'academic_year_id' => $yearId > 0 ? $yearId : null,
            'term_id' => $termId,
            'title' => $title,
            'event_type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'description' => $description !== '' ? $description : null,
            'audience' => 'all',
        ];
        $id = AcademicManagement::saveEvent($schoolId, $data, (int)(Auth::user()['id'] ?? 0));
        audit('academics', 'create_academic_event', 'academic_events', $id,
            'Added academic event "' . $title . '"', null, $data);
        $this->respond(true, 'Event added to the academic calendar.', 'academics', $params);
    }

    public function saveSyllabus(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $subjectId = $this->positiveId($_POST['subject_id'] ?? null);
        $curriculumId = $this->optionalId($_POST['curriculum_id'] ?? null);
        $title = trim((string)($_POST['title'] ?? ''));
        $level = (string)($_POST['level'] ?? 'O-Level');
        $version = trim((string)($_POST['version'] ?? ''));
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;

        $curriculumValid = $curriculumId === null || AcademicManagement::curriculum($curriculumId, $schoolId) !== null;
        if (!AcademicManagement::subject($subjectId, $schoolId)
            || !$curriculumValid
            || $title === '' || mb_strlen($title) > 180
            || !in_array($level, ['O-Level', 'A-Level'], true)
            || mb_strlen($version) > 30) {
            $this->respond(false, 'Choose a subject and enter a syllabus title with a valid level.', 'academics', [],
                ['title' => 'A syllabus title and an O-Level or A-Level choice are required.']);
        }

        $data = [
            'subject_id' => $subjectId, 'curriculum_id' => $curriculumId, 'title' => $title,
            'level' => $level, 'version' => $version !== '' ? $version : null, 'is_current' => $isCurrent,
        ];
        $id = AcademicManagement::saveSyllabus($schoolId, $data, (int)(Auth::user()['id'] ?? 0));
        audit('academics', 'create_syllabus', 'syllabuses', $id, 'Created syllabus "' . $title . '"', null, $data);
        $this->respond(true, 'Syllabus saved. Add its topics to track coverage.', 'academics', ['syllabus_id' => $id]);
    }

    public function saveSyllabusTopic(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $syllabusId = $this->positiveId($_POST['syllabus_id'] ?? null);
        $syllabus = $syllabusId > 0 ? AcademicManagement::syllabus($syllabusId, $schoolId) : null;
        if (!$syllabus) {
            $this->respond(false, 'Syllabus not found.', 'academics', [], ['title' => 'Syllabus not found.']);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $order = filter_var($_POST['order_index'] ?? '0', FILTER_VALIDATE_INT);
        $lessonsRaw = trim((string)($_POST['suggested_lessons'] ?? ''));
        $subTopics = trim((string)($_POST['sub_topics'] ?? ''));
        $outcomes = trim((string)($_POST['learning_outcomes'] ?? ''));
        $lessons = null;
        $lessonsValid = true;
        if ($lessonsRaw !== '') {
            $lessonsValid = $lessonsRaw !== ''
                && filter_var($lessonsRaw, FILTER_VALIDATE_INT) !== false
                && (int)$lessonsRaw >= 0 && (int)$lessonsRaw <= 32767;
            if ($lessonsValid) {
                $lessons = (int)$lessonsRaw;
            }
        }

        if ($title === '' || mb_strlen($title) > 180
            || mb_strlen($code) > 20
            || $order === false || $order < 0 || $order > 32767
            || mb_strlen($subTopics) > 65535 || mb_strlen($outcomes) > 65535
            || !$lessonsValid) {
            $this->respond(false, 'Enter a valid topic title, order, and lesson estimate.', 'academics',
                ['syllabus_id' => $syllabusId],
                ['title' => 'A topic title is required (maximum 180 characters).']);
        }

        $data = [
            'order_index' => $order, 'code' => $code !== '' ? $code : null, 'title' => $title,
            'sub_topics' => $subTopics !== '' ? $subTopics : null,
            'learning_outcomes' => $outcomes !== '' ? $outcomes : null,
            'suggested_lessons' => $lessons, 'is_project_topic' => isset($_POST['is_project_topic']) ? 1 : 0,
        ];
        $id = AcademicManagement::saveSyllabusTopic($syllabusId, $data, (int)(Auth::user()['id'] ?? 0));
        audit('academics', 'create_syllabus_topic', 'syllabus_topics', $id,
            'Added syllabus topic "' . $title . '"', null, $data);
        $this->respond(true, 'Syllabus topic added.', 'academics', ['syllabus_id' => $syllabusId]);
    }

    public function saveCoverage(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $subjectId = $this->positiveId($_POST['subject_id'] ?? null);
        $classId = $this->positiveId($_POST['class_id'] ?? null);
        $streamId = $this->optionalId($_POST['stream_id'] ?? null);
        $yearId = $this->positiveId($_POST['academic_year_id'] ?? null);
        $termId = $this->positiveId($_POST['term_id'] ?? null);
        $params = [
            'year_id' => $yearId, 'coverage_subject_id' => $subjectId, 'coverage_class_id' => $classId,
            'coverage_stream_id' => $streamId ?? 0, 'coverage_term_id' => $termId,
        ];

        if (!AcademicManagement::subject($subjectId, $schoolId)
            || !$this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            || !$this->validStream($streamId, $classId, $schoolId)
            || !$this->validYearTerm($yearId, $termId, $schoolId)) {
            $this->respond(false, 'Choose a subject, class, year, and term belonging to this school.', 'academics', $params,
                ['coverage_subject_id' => 'Choose a subject, class, and term to record coverage.']);
        }

        $topics = AcademicManagement::syllabusTopics(
            (int)(AcademicManagement::currentSyllabus($schoolId, $subjectId)['id'] ?? 0)
        );
        if (!$topics) {
            $this->respond(false, 'Add the current syllabus and its topics for this subject before recording coverage.', 'academics', $params);
        }

        $topicIds = [];
        foreach ($topics as $topic) {
            $topicIds[] = (int)$topic['id'];
        }

        $postedTaught = $_POST['taught'] ?? [];
        $postedDates = $_POST['taught_date'] ?? [];
        if (!is_array($postedTaught) || !is_array($postedDates) || count($postedTaught) > 500 || count($postedDates) > 500) {
            $this->respond(false, 'The submitted coverage form is invalid.', 'academics', $params);
        }
        $taughtIds = [];
        foreach ($postedTaught as $value) {
            $parsed = filter_var($value, FILTER_VALIDATE_INT);
            if ($parsed === false || !in_array((int)$parsed, $topicIds, true)) {
                $this->respond(false, 'The submitted coverage form contains an unknown topic.', 'academics', $params);
            }
            $taughtIds[] = (int)$parsed;
        }

        $userId = (int)(Auth::user()['id'] ?? 0);
        foreach ($topicIds as $topicId) {
            if (in_array($topicId, $taughtIds, true)) {
                $taughtDate = trim((string)($postedDates[$topicId] ?? ''));
                if ($taughtDate === '') {
                    $taughtDate = date('Y-m-d');
                }
                if (!$this->requiredDate($taughtDate)) {
                    $this->respond(false, 'Each taught topic needs a valid taught date.', 'academics', $params,
                        ['taught_date_' . $topicId => 'Enter a valid date.']);
                }
                AcademicManagement::saveCoverage($topicId, $classId, $streamId, $yearId, $termId, $taughtDate, $userId);
            } else {
                AcademicManagement::removeCoverage($topicId, $classId, $streamId, $termId);
            }
        }

        audit('academics', 'record_syllabus_coverage', 'syllabus_coverage', null,
            'Recorded coverage for ' . count($taughtIds) . ' of ' . count($topicIds) . ' topics', null,
            ['subject_id' => $subjectId, 'class_id' => $classId, 'term_id' => $termId, 'taught' => $taughtIds]);
        $this->respond(true, 'Syllabus coverage updated for this class and term.', 'academics', $params);
    }

    public function saveActivity(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $subjectId = $this->optionalId($_POST['subject_id'] ?? null);
        $classId = $this->positiveId($_POST['class_id'] ?? null);
        $streamId = $this->optionalId($_POST['stream_id'] ?? null);
        $yearId = $this->positiveId($_POST['academic_year_id'] ?? null);
        $termId = $this->positiveId($_POST['term_id'] ?? null);
        $title = trim((string)($_POST['title'] ?? ''));
        $type = (string)($_POST['activity_type'] ?? 'project');
        $description = trim((string)($_POST['description'] ?? ''));
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        $score = filter_var($_POST['max_score'] ?? '3', FILTER_VALIDATE_INT);
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $status = (string)($_POST['status'] ?? 'planned');
        $types = ['project', 'practical', 'integrated_activity', 'field_work', 'presentation'];
        $statuses = ['planned', 'assigned', 'in_progress', 'submitted', 'marked'];

        $subjectValid = $subjectId === null || AcademicManagement::subject($subjectId, $schoolId) !== null;
        if (!$subjectValid
            || !$this->schoolHas('classes', $classId, $schoolId, 'is_active = 1')
            || !$this->validStream($streamId, $classId, $schoolId)
            || !$this->validYearTerm($yearId, $termId, $schoolId)
            || $title === '' || mb_strlen($title) > 180
            || !in_array($type, $types, true)
            || !in_array($status, $statuses, true)
            || mb_strlen($description) > 65535 || mb_strlen($instructions) > 65535
            || $score === false || $score < 1 || $score > 127
            || !$this->optionalDate($startDate) || !$this->optionalDate($dueDate)
            || ($startDate !== '' && $dueDate !== '' && strtotime($dueDate) < strtotime($startDate))) {
            $this->respond(false, 'Enter a valid activity with a class, term, title, type, and score.', 'academics',
                ['year_id' => $yearId],
                ['due_date' => 'The due date must be a valid date on or after the start date.']);
        }

        $data = [
            'subject_id' => $subjectId, 'class_id' => $classId, 'stream_id' => $streamId,
            'academic_year_id' => $yearId, 'term_id' => $termId, 'title' => $title,
            'activity_type' => $type, 'description' => $description !== '' ? $description : null,
            'instructions' => $instructions !== '' ? $instructions : null, 'max_score' => $score,
            'start_date' => $startDate !== '' ? $startDate : null, 'due_date' => $dueDate !== '' ? $dueDate : null,
            'status' => $status,
        ];
        $id = AcademicManagement::saveActivity($schoolId, $data, (int)(Auth::user()['id'] ?? 0));
        audit('academics', 'create_integrated_activity', 'integrated_activities', $id,
            'Created integrated activity "' . $title . '"', null, $data);
        $this->respond(true, 'Integrated activity saved.', 'academics', ['year_id' => $yearId]);
    }

    public function updateActivityStatus(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $schoolId = school_id();
        $id = $this->positiveId($_POST['id'] ?? null);
        $status = (string)($_POST['status'] ?? '');
        $statuses = ['planned', 'assigned', 'in_progress', 'submitted', 'marked'];
        $row = $id > 0 ? AcademicManagement::activity($id, $schoolId) : null;
        if (!$row || !in_array($status, $statuses, true)) {
            $this->respond(false, 'Choose a valid activity and status.', 'academics');
        }
        AcademicManagement::setActivityStatus($id, $schoolId, $status);
        audit('academics', 'update_integrated_activity_status', 'integrated_activities', $id,
            'Changed activity status to ' . $status, $row, ['status' => $status]);
        $this->respond(true, 'Activity status updated.', 'academics', ['year_id' => (int)$row['academic_year_id']]);
    }

    protected function allowed(string $module = 'academics', string $action = 'view'): bool
    {
        if (Auth::can('academics', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to perform this academics action.', 'dashboard');
        return false;
    }

    private function requiredDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validTime(string $value): bool
    {
        $time = DateTime::createFromFormat('!H:i', $value);
        return $time !== false && $time->format('H:i') === $value;
    }

    private function allocationInput(int $schoolId): array
    {
        return [
            $this->positiveId($_POST['subject_id'] ?? null),
            $this->positiveId($_POST['class_id'] ?? null),
            $this->optionalId($_POST['stream_id'] ?? null),
            $this->positiveId($_POST['academic_year_id'] ?? null),
            $this->optionalId($_POST['term_id'] ?? null),
        ];
    }

    private function schoolHas(string $table, int $id, int $schoolId, string $extra = ''): bool
    {
        $allowed = ['classes'];
        if (!in_array($table, $allowed, true)) {
            return false;
        }
        return (bool)AcademicManagement::one(
            'SELECT id FROM ' . $table . ' WHERE id = ? AND school_id = ?' . ($extra !== '' ? ' AND ' . $extra : ''),
            [$id, $schoolId]
        );
    }

    private function validStream(?int $streamId, int $classId, int $schoolId): bool
    {
        if ($streamId === null) {
            return true;
        }
        return (bool)AcademicManagement::one(
            'SELECT st.id FROM streams st
               JOIN classes c ON c.id = st.class_id
              WHERE st.id = ? AND st.class_id = ? AND st.is_active = 1 AND c.school_id = ?',
            [$streamId, $classId, $schoolId]
        );
    }

    private function validYearTerm(int $yearId, ?int $termId, int $schoolId): bool
    {
        if (!AcademicManagement::year($yearId, $schoolId)) {
            return false;
        }
        return $termId === null || AcademicManagement::term($termId, $schoolId, $yearId) !== null;
    }

    private function subjectOffered(int $subjectId, int $classId, ?int $streamId, int $yearId, ?int $termId): bool
    {
        return (bool)AcademicManagement::one(
            'SELECT id FROM subject_allocations
              WHERE subject_id = ? AND class_id = ? AND academic_year_id = ? AND is_active = 1
                AND (stream_id IS NULL OR stream_id <=> ?)
                AND (term_id IS NULL OR term_id <=> ?)
              LIMIT 1',
            [$subjectId, $classId, $yearId, $streamId, $termId]
        );
    }

    private function teacherAssigned(
        int $staffId,
        int $subjectId,
        int $classId,
        ?int $streamId,
        int $yearId,
        int $termId
    ): bool {
        return (bool)AcademicManagement::one(
            'SELECT id FROM teacher_allocations
              WHERE staff_id = ? AND subject_id = ? AND class_id = ? AND academic_year_id = ?
                AND (stream_id IS NULL OR stream_id <=> ?)
                AND (term_id IS NULL OR term_id <=> ?)
              LIMIT 1',
            [$staffId, $subjectId, $classId, $yearId, $streamId, $termId]
        );
    }

    private function optionalDate(string $value): bool
    {
        if ($value === '') {
            return true;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validCode(string $code, int $maxLength): bool
    {
        return $code !== '' && mb_strlen($code) <= $maxLength
            && preg_match('/^[A-Z0-9][A-Z0-9._-]*$/', $code) === 1;
    }

    private function optionalId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        return $parsed !== false && $parsed > 0 ? $parsed : null;
    }

    private function positiveId(mixed $value): int
    {
        return $this->optionalId($value) ?? 0;
    }
}
