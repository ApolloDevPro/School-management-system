<?php
declare(strict_types=1);

final class ExaminationController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $exams = Examination::examsForSchool($schoolId);
        $examId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
        $exam = $examId > 0 ? Examination::examInSchool($examId, $schoolId) : null;
        if (!$exam && $exams) {
            $exam = $exams[0];
            $examId = (int)$exam['id'];
        }

        $schedules = $exam ? Examination::schedules($examId, $schoolId) : [];
        $scheduleId = (int)($_GET['schedule_id'] ?? ($schedules[0]['id'] ?? 0));
        $schedule = null;
        foreach ($schedules as $row) {
            if ((int)$row['id'] === $scheduleId) {
                $schedule = $row;
                break;
            }
        }
        if (!$schedule && $schedules) {
            $schedule = $schedules[0];
            $scheduleId = (int)$schedule['id'];
        }
        if ($schedule) {
            $schedule['school_id'] = $schoolId;
        }

        $years = Examination::years($schoolId);
        $yearId = (int)($exam['academic_year_id'] ?? ($years[0]['id'] ?? 0));
        $terms = $yearId > 0 ? Examination::terms($yearId, $schoolId) : [];
        $formClassId = (int)($_GET['class_id'] ?? ($schedule['class_id'] ?? 0));

        $this->render('examinations/index', [
            'pageTitle' => 'Examinations and assessment',
            'activeNav' => 'examinations',
            'exams' => $exams,
            'exam' => $exam,
            'schedules' => $schedules,
            'schedule' => $schedule,
            'students' => $schedule ? Examination::studentsForSchedule($schedule) : [],
            'years' => $years,
            'terms' => $terms,
            'classes' => Examination::classes($schoolId),
            'formClassId' => $formClassId,
            'subjects' => $exam && $formClassId > 0
                ? Examination::subjectsForClass($formClassId, $yearId, (int)$exam['term_id'], $schoolId)
                : [],
            'streams' => $formClassId > 0 ? Examination::streams($formClassId) : [],
            'scales' => Examination::scales($schoolId),
            'editTerms' => $terms,
            'canEdit' => Auth::can('students', 'edit'),
            'canAdd' => Auth::can('students', 'add'),
        ]);
    }

    public function saveExam(): void
    {
        $schoolId = school_id();
        $id = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? Examination::examInSchool($id, $schoolId) : null;
        if ($id > 0 && !$existing) {
            $this->respond(false, 'That exam was not found in this school.', 'examinations');
        }
        if ($existing && !in_array($existing['status'], ['planning', 'scheduled'], true)) {
            $this->respond(false, 'This exam can no longer be edited.', 'examinations', ['exam_id' => $id]);
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['exam_type'] ?? '');
        $scope = (string)($_POST['level_scope'] ?? '');
        $yearId = (int)($_POST['academic_year_id'] ?? 0);
        $termId = (int)($_POST['term_id'] ?? 0);
        $year = Examination::one(
            'SELECT id FROM academic_years WHERE id = ? AND school_id = ?',
            [$yearId, $schoolId]
        );
        $term = Examination::one(
            'SELECT t.id FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
              WHERE t.id = ? AND t.academic_year_id = ? AND y.school_id = ?',
            [$termId, $yearId, $schoolId]
        );
        $maxMark = filter_var($_POST['max_mark'] ?? null, FILTER_VALIDATE_INT);
        $validTypes = ['midterm', 'end_of_term', 'mock', 'entrance', 'remedial', 'assignment', 'final'];
        if ($name === '' || mb_strlen($name) > 120 || !$year || !$term
            || !in_array($type, $validTypes, true) || !in_array($scope, ['O-Level', 'A-Level', 'Both'], true)
            || $maxMark === false || $maxMark < 1 || $maxMark > 32767) {
            $this->respond(false, 'Enter a name, valid school year and term, exam type, level and maximum mark.', 'examinations', [], ['exam' => 'Check the required fields.']);
        }

        $start = trim((string)($_POST['start_date'] ?? '')) ?: null;
        $end = trim((string)($_POST['end_date'] ?? '')) ?: null;
        if (($start !== null && !$this->validDate($start)) || ($end !== null && !$this->validDate($end))
            || ($start && $end && $start > $end)) {
            $this->respond(false, 'Enter valid exam dates; the end date cannot precede the start date.', 'examinations');
        }
        $data = [
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'name' => $name,
            'exam_type' => $type,
            'level_scope' => $scope,
            'start_date' => $start,
            'end_date' => $end,
            'max_mark' => $maxMark,
            'notes' => mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 255) ?: null,
        ];
        if ($existing) {
            unset($data['school_id']);
            Examination::updateRow($id, $data);
            audit('examinations', 'update', 'exams', $id, 'Updated exam ' . $name);
        } else {
            $data['created_by'] = (int)(Auth::user()['id'] ?? 0) ?: null;
            $data['status'] = 'planning';
            $id = Examination::insert($data);
            audit('examinations', 'create', 'exams', $id, 'Created exam ' . $name);
        }
        $this->respond(true, 'Exam saved.', 'examinations', ['exam_id' => $id]);
    }

    public function saveSchedule(): void
    {
        $schoolId = school_id();
        $examId = (int)($_POST['exam_id'] ?? 0);
        $exam = Examination::examInSchool($examId, $schoolId);
        if (!$exam || !in_array($exam['status'], ['planning', 'scheduled'], true)) {
            $this->respond(false, 'Choose an exam that is still being scheduled.', 'examinations');
        }
        $classId = (int)($_POST['class_id'] ?? 0);
        $class = Examination::one(
            'SELECT * FROM classes WHERE id = ? AND school_id = ? AND is_active = 1',
            [$classId, $schoolId]
        );
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $subjects = Examination::subjectsForClass(
            $classId,
            (int)$exam['academic_year_id'],
            (int)$exam['term_id'],
            $schoolId
        );
        $subjectIds = array_map(static fn(array $row): int => (int)$row['id'], $subjects);
        $streamId = (int)($_POST['stream_id'] ?? 0) ?: null;
        $stream = $streamId ? Examination::one(
            'SELECT id FROM streams WHERE id = ? AND class_id = ? AND is_active = 1',
            [$streamId, $classId]
        ) : true;
        $date = trim((string)($_POST['paper_date'] ?? ''));
        $startTime = trim((string)($_POST['start_time'] ?? ''));
        $endTime = trim((string)($_POST['end_time'] ?? ''));
        $maxMark = filter_var($_POST['max_mark'] ?? null, FILTER_VALIDATE_INT);
        $classInScope = $class && ($exam['level_scope'] === 'Both' || $exam['level_scope'] === $class['level']);
        if (!$classInScope || !in_array($subjectId, $subjectIds, true) || !$stream
            || !$this->validDate($date) || !$this->validTime($startTime) || !$this->validTime($endTime)
            || $startTime >= $endTime || ($exam['start_date'] && $date < $exam['start_date'])
            || ($exam['end_date'] && $date > $exam['end_date'])
            || $maxMark === false || $maxMark < 1 || $maxMark > 32767) {
            $this->respond(false, 'Choose an allocated class and subject, valid stream, date, time range and maximum mark.', 'examinations', ['exam_id' => $examId]);
        }
        $duplicate = Examination::one(
            'SELECT id FROM exam_schedule
              WHERE exam_id = ? AND subject_id = ? AND class_id = ? AND stream_id <=> ?',
            [$examId, $subjectId, $classId, $streamId]
        );
        if ($duplicate) {
            $this->respond(false, 'This subject/class/stream already has a schedule in this exam.', 'examinations', ['exam_id' => $examId]);
        }

        try {
            $scheduleId = Examination::insert([
                'exam_id' => $examId,
                'subject_id' => $subjectId,
                'class_id' => $classId,
                'stream_id' => $streamId,
                'paper_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'max_mark' => $maxMark,
                'syllabus_scope' => mb_substr(trim((string)($_POST['syllabus_scope'] ?? '')), 0, 255) ?: null,
                'notes' => mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 255) ?: null,
            ]);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'This subject/class/stream already has a schedule in this exam.', 'examinations', ['exam_id' => $examId]);
            }
            throw $error;
        }
        Examination::execute("UPDATE exams SET status = 'scheduled' WHERE id = ? AND status = 'planning'", [$examId]);
        audit('examinations', 'schedule', 'exam_schedule', $scheduleId, 'Scheduled exam paper for class ' . $class['name']);
        $this->respond(true, 'Exam paper scheduled.', 'examinations', ['exam_id' => $examId, 'schedule_id' => $scheduleId]);
    }

    public function saveMark(): void
    {
        $schoolId = school_id();
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $schedule = Examination::scheduleInSchool($scheduleId, $schoolId);
        if (!$schedule || in_array($schedule['exam_status'], ['cancelled', 'results_ready', 'published'], true)) {
            $this->respond(false, 'Marks cannot be entered for this exam paper.', 'examinations');
        }
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student = Examination::one(
            'SELECT id, current_stream_id FROM students
              WHERE id = ? AND school_id = ? AND current_class_id = ? AND status = \'active\'
                AND (? IS NULL OR current_stream_id = ?)',
            [$studentId, $schoolId, $schedule['class_id'], $schedule['stream_id'], $schedule['stream_id']]
        );
        if (!$student) {
            $this->respond(false, 'Choose an active student assigned to this scheduled class/stream.', 'examinations', ['exam_id' => $schedule['exam_id'], 'schedule_id' => $scheduleId]);
        }

        $examRaw = trim((string)($_POST['exam_mark'] ?? ''));
        $caRaw = trim((string)($_POST['ca_mark'] ?? ''));
        $caMaxRaw = trim((string)($_POST['max_ca_mark'] ?? ''));
        $examMark = $this->nullableNumber($examRaw);
        $caMark = $this->nullableNumber($caRaw);
        $caMax = $this->nullableNumber($caMaxRaw);
        $remarks = mb_substr(trim((string)($_POST['remarks'] ?? '')), 0, 255) ?: null;
        if ($examMark === null || $examRaw === '' || $examMark < 0 || $examMark > (float)$schedule['max_mark']
            || ($caRaw !== '' && $caMark === null) || ($caMaxRaw !== '' && $caMax === null)
            || (($caMark === null) !== ($caMax === null))
            || ($caMark !== null && ($caMark < 0 || $caMax <= 0 || $caMark > $caMax))) {
            $this->respond(false, 'Exam mark is required and must be within its maximum. Enter a valid CA score and maximum together.', 'examinations', ['exam_id' => $schedule['exam_id'], 'schedule_id' => $scheduleId]);
        }

        $existing = Examination::markForStudent((int)$schedule['exam_id'], $studentId, (int)$schedule['subject_id']);
        if ($existing && !in_array($existing['status'], ['draft', 'reopened'], true)) {
            $this->respond(false, 'Only draft or reopened marks can be edited. Use the mark workflow to reopen it first.', 'examinations', ['exam_id' => $schedule['exam_id'], 'schedule_id' => $scheduleId]);
        }
        $total = $examMark + ($caMark ?? 0);
        $maximum = (float)$schedule['max_mark'] + ($caMax ?? 0);
        $userId = (int)(Auth::user()['id'] ?? 0) ?: null;
        try {
            Examination::saveMark([
                'exam_id' => (int)$schedule['exam_id'],
                'student_id' => $studentId,
                'subject_id' => (int)$schedule['subject_id'],
                'class_id' => (int)$schedule['class_id'],
                'stream_id' => $student['current_stream_id'],
                'teacher_id' => null,
                'ca_mark' => $caMark,
                'max_ca_mark' => $caMax,
                'exam_mark' => $examMark,
                'max_exam_mark' => (float)$schedule['max_mark'],
                'total_mark' => round($total, 2),
                'percentage' => round(($total / $maximum) * 100, 2),
                'source' => 'manual',
                'remarks' => $remarks,
                'entered_by' => $userId,
            ]);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'examinations', ['exam_id' => $schedule['exam_id'], 'schedule_id' => $scheduleId]);
        }
        audit('examinations', 'enter_mark', 'marks', $studentId, 'Saved mark for exam ' . $schedule['exam_id'] . ', subject ' . $schedule['subject_id']);
        $this->respond(true, 'Mark saved as draft.', 'examinations', ['exam_id' => $schedule['exam_id'], 'schedule_id' => $scheduleId]);
    }

    public function markAction(): void
    {
        $schoolId = school_id();
        $markId = (int)($_POST['mark_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 255);
        try {
            $change = Examination::changeMarkStatus(
                $markId,
                $schoolId,
                $action,
                (int)(Auth::user()['id'] ?? 0),
                $reason
            );
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'examinations');
        }
        $mark = $change['mark'];
        audit('examinations', 'mark_' . $action, 'marks', $markId, 'Mark status changed to ' . $change['status'] . ($reason ? ': ' . $reason : ''));
        $this->respond(true, 'Mark status changed to ' . $change['status'] . '.', 'examinations', ['exam_id' => $mark['exam_id']]);
    }

    public function processResults(): void
    {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $scales = [
            'O-Level' => (int)($_POST['o_level_scale_id'] ?? 0),
            'A-Level' => (int)($_POST['a_level_scale_id'] ?? 0),
        ];
        try {
            $processed = Examination::processResults($examId, school_id(), $scales);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'examinations', ['exam_id' => $examId]);
        }
        audit('examinations', 'process_results', 'exams', $examId, 'Processed ' . $processed['marks'] . ' marks for ' . $processed['students'] . ' students');
        $this->respond(true, 'Processed ' . $processed['marks'] . ' subject mark(s) for ' . $processed['students'] . ' student(s).', 'examinations', ['exam_id' => $examId]);
    }

    public function publishResults(): void
    {
        $examId = (int)($_POST['exam_id'] ?? 0);
        $exam = Examination::examInSchool($examId, school_id());
        if (!$exam || $exam['status'] !== 'results_ready') {
            $this->respond(false, 'Process results before publishing this exam.', 'examinations', ['exam_id' => $examId]);
        }
        Examination::execute(
            "UPDATE exams SET status = 'published', published_at = NOW() WHERE id = ? AND school_id = ? AND status = 'results_ready'",
            [$examId, school_id()]
        );
        audit('examinations', 'publish_results', 'exams', $examId, 'Published results for ' . $exam['name']);
        $this->respond(true, 'Results published.', 'examinations', ['exam_id' => $examId]);
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validTime(string $value): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value) === 1;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float)$value;
        return is_finite($number) && $number <= 999999 ? $number : null;
    }

    public function examTimetable(): void
    {
        $schoolId = school_id();
        $examId = (int)($_GET['exam_id'] ?? 0);
        $db = Database::conn();
        $exam = $db->query(
            'SELECT e.*, y.name AS year_name, t.name AS term_name
               FROM exams e
               JOIN academic_years y ON y.id = e.academic_year_id
               JOIN terms t ON t.id = e.term_id
              WHERE e.id = ? AND e.school_id = ?',
            [$examId, $schoolId]
        )->fetch();
        if (!$exam) {
            $this->respond(false, 'Exam not found.', 'examinations');
            return;
        }
        $schedules = $db->query(
            'SELECT es.*, sub.code AS subject_code, sub.name AS subject_name,
                    c.name AS class_name, st.name AS stream_name
               FROM exam_schedule es
               JOIN subjects sub ON sub.id = es.subject_id
               JOIN classes c ON c.id = es.class_id
               LEFT JOIN streams st ON st.id = es.stream_id
              WHERE es.exam_id = ?
              ORDER BY es.paper_date, es.start_time, c.name, sub.name',
            [$examId]
        );
        $school = school_row() ?? [];
        $html = PdfService::examTimetableHtml($school, $exam, $schedules, [
            'academic_year' => $exam['year_name'] ?? '-',
            'term' => $exam['term_name'] ?? '-',
            'level' => $exam['class_level'] ?? '-',
        ]);
        PdfService::output($html, 'timetable_'.$exam['name'].'_'.$examId.'.pdf');
    }

    public function resultSlip(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $examId = (int)($_GET['exam_id'] ?? 0);
        $db = Database::conn();
        $student = $db->query(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        )->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'examinations');
            return;
        }
        $exam = $db->query(
            'SELECT e.*, y.name AS year_name, t.name AS term_name
               FROM exams e
               JOIN academic_years y ON y.id = e.academic_year_id
               JOIN terms t ON t.id = e.term_id
              WHERE e.id = ? AND e.school_id = ?',
            [$examId, $schoolId]
        )->fetch();
        if (!$exam) {
            $this->respond(false, 'Exam not found.', 'examinations');
            return;
        }
        $results = $db->query(
            'SELECT sr.*, sub.code AS subject_code, sub.name AS subject_name,
                    m.ca_mark, m.exam_mark
               FROM subject_results sr
               JOIN subjects sub ON sub.id = sr.subject_id
               LEFT JOIN marks m ON m.exam_id = sr.exam_id AND m.student_id = sr.student_id
                   AND m.subject_id = sr.subject_id
              WHERE sr.exam_id = ? AND sr.student_id = ? AND sr.school_id = ?
              ORDER BY sr.weighted_percent DESC',
            [$examId, $studentId, $schoolId]
        );
        $school = school_row() ?? [];
        $html = PdfService::resultSlipHtml($student, $exam, $results, $school, [
            'academic_year' => $exam['year_name'] ?? '-',
            'term' => $exam['term_name'] ?? '-',
        ]);
        PdfService::output($html, 'result_slip_'.$student['admission_number'].'_'.$examId.'.pdf');
    }
}
