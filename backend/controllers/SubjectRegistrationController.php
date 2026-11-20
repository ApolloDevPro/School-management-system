<?php
declare(strict_types=1);

/**
 * Student subject and A-Level combination registration, including the JSON API endpoints.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class SubjectRegistrationController extends Controller
{
    public function index(): void
    {
        $schoolId  = school_id();
        $year      = AcademicYear::active($schoolId);
        $term      = Term::activeForSchool($schoolId);
        $yearId    = $year ? (int)$year['id'] : 0;
        $termId    = $term ? (int)$term['id'] : null;

        $classes = AcademicManagement::classes($schoolId);
        $streams = AcademicManagement::streams($schoolId);

        $classId   = (int)($_GET['class_id'] ?? 0);
        $streamId  = (int)($_GET['stream_id'] ?? 0);
        $studentId = (int)($_GET['student_id'] ?? 0);
        $tab       = ($_GET['tab'] ?? '') === 'bulk' ? 'bulk' : 'student';

        // Keep the class and stream filters aligned with the chosen student.
        $student = $studentId > 0 ? Student::findFull($studentId) : null;
        if ($student && (int)$student['school_id'] !== $schoolId) {
            $student = null;
        }
        if ($student) {
            if ($classId === 0) {
                $classId = (int)($student['current_class_id'] ?? 0);
            }
            if ($streamId === 0) {
                $streamId = (int)($student['current_stream_id'] ?? 0);
            }
        }

        // Only offer class/stream filters that belong to this school.
        $classIds = array_map('intval', array_column($classes, 'id'));
        if ($classId > 0 && !in_array($classId, $classIds, true)) {
            $classId = 0;
        }
        if ($streamId > 0) {
            $streamClassId = 0;
            foreach ($streams as $stream) {
                if ((int)$stream['id'] === $streamId) {
                    $streamClassId = (int)$stream['class_id'];
                    break;
                }
            }
            if ($streamClassId === 0 || ($classId > 0 && $streamClassId !== $classId)) {
                $streamId = 0;
            } elseif ($classId === 0) {
                $classId = $streamClassId;
            }
        }

        // A student only belongs to the selected class and stream.
        if ($student && $classId > 0 && (int)($student['current_class_id'] ?? 0) !== $classId) {
            $student = null;
        }
        if ($student && $streamId > 0 && (int)($student['current_stream_id'] ?? 0) !== $streamId) {
            $student = null;
        }

        $allocated     = [];
        $registeredMap = [];
        $combination   = null;
        $combinations  = [];
        $roster        = [];
        $bulkSubjects  = [];
        $bulkStudents  = [];
        $offeredCounts = [];

        if ($yearId > 0) {
            if ($classId > 0) {
                $roster = StudentSubject::studentsInClass($schoolId, $classId, $streamId ?: null);
                $offeredCounts[0] = count(StudentSubject::allocatedSubjects($classId, $yearId, $termId, null));
                foreach ($streams as $stream) {
                    if ((int)$stream['class_id'] === $classId) {
                        $offeredCounts[(int)$stream['id']] = count(
                            StudentSubject::allocatedSubjects($classId, $yearId, $termId, (int)$stream['id'])
                        );
                    }
                }
            }

            if ($tab === 'bulk' && $classId > 0) {
                $bulkSubjects = StudentSubject::allocatedSubjects($classId, $yearId, $termId, $streamId ?: null);
                $bulkStudents = $roster;
            }
        }

        if ($student && $yearId > 0) {
            $combination  = StudentSubject::combinationOf($studentId, $yearId);
            $allocated    = $this->offeredSubjects($student, $yearId, $termId, $combination);
            $combinations = $student['class_level'] === 'A-Level'
                ? AlevelCombination::activeForSchool($schoolId)
                : [];

            foreach (StudentSubject::registrationsOf($studentId, $yearId) as $row) {
                $registeredMap[(int)$row['subject_id']] = $row;
            }
        }

        $counts = $yearId > 0 ? StudentSubject::registrationCounts($schoolId, $yearId) : [];

        $this->render('students/subjects', [
            'pageTitle'    => 'Subject registration',
            'activeNav'    => 'students_subjects',
            'year'         => $year,
            'term'         => $term,
            'classes'      => $classes,
            'streams'      => $streams,
            'classId'      => $classId,
            'streamId'     => $streamId,
            'tab'          => $tab,
            'student'      => $student,
            'allocated'    => $allocated,
            'registeredMap'=> $registeredMap,
            'combination'  => $combination,
            'combinations' => $combinations,
            'roster'       => $roster,
            'counts'       => $counts,
            'offeredCounts'=> $offeredCounts,
            'bulkSubjects' => $bulkSubjects,
            'bulkStudents' => $bulkStudents,
            'isALevel'     => $student && ($student['class_level'] ?? '') === 'A-Level',
            'canEdit'      => Auth::can('students', 'edit'),
        ]);
    }

    public function save(): void
    {
        $schoolId  = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = Student::find($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            $this->respond(false, 'Choose a valid student.', 'students/subjects');
        }

        $year = AcademicYear::active($schoolId);
        if (!$year) {
            $this->respond(false, 'No active academic year. Set one in Administration first.', 'students/subjects');
        }

        $yearId  = (int)$year['id'];
        $term    = Term::activeForSchool($schoolId);
        $termId  = $term ? (int)$term['id'] : null;
        $classId = (int)($student['current_class_id'] ?? 0);

        if ($classId === 0) {
            $this->respond(false, 'Allocate the student to a class before registering subjects.', 'students/profile', ['id' => $studentId]);
        }

        $full         = Student::findFull($studentId);
        $combination  = StudentSubject::combinationOf($studentId, $yearId);
        $allocatedMap = [];
        foreach ($this->offeredSubjects($full ?? $student, $yearId, $termId, $combination) as $subject) {
            $allocatedMap[(int)$subject['id']] = (int)$subject['is_compulsory'];
        }
        if (empty($allocatedMap)) {
            $this->respond(false, 'No subjects are allocated to this class yet. Ask an administrator to set up subject allocations in Academics.', 'students/subjects', ['student_id' => $studentId]);
        }

        $chosen = array_map('intval', (array)($_POST['subjects'] ?? []));
        $chosen = array_values(array_intersect($chosen, array_keys($allocatedMap)));

        // Compulsory subjects are always kept, even when the checkbox is locked.
        foreach ($allocatedMap as $subjectId => $isCompulsory) {
            if ($isCompulsory === 1 && !in_array($subjectId, $chosen, true)) {
                $chosen[] = $subjectId;
            }
        }

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $chosen, (int)($student['current_stream_id'] ?? 0) ?: null);
        if (!empty($clashes)) {
            $this->respond(false, 'Timetable clashes detected: ' . implode('; ', array_column($clashes, 'message')), 'students/subjects', ['student_id' => $studentId]);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            foreach ($chosen as $subjectId) {
                StudentSubject::register($studentId, $subjectId, $yearId, $termId, $allocatedMap[$subjectId] === 1);
            }

            foreach (StudentSubject::registrationsOf($studentId, $yearId) as $row) {
                $subjectId = (int)$row['subject_id'];
                if ($row['status'] === 'registered' && !in_array($subjectId, $chosen, true)) {
                    StudentSubject::drop($studentId, $subjectId, $yearId, 'Removed during registration');
                }
            }

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'register_subjects', 'student_subjects', $studentId,
            'Registered ' . count($chosen) . ' subject(s) for ' . $student['admission_number']);

        $this->respond(true, count($chosen) . ' subject(s) registered.', 'students/subjects', ['student_id' => $studentId]);
    }

    public function saveCombination(): void
    {
        $schoolId  = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = Student::findFull($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            $this->respond(false, 'Choose a valid student.', 'students/subjects');
        }

        $combinationId = (int)($_POST['combination_id'] ?? 0);
        $combination   = $combinationId > 0 ? AlevelCombination::findInSchool($combinationId, $schoolId) : null;
        if (!$combination) {
            $this->respond(false, 'Choose a valid combination.', 'students/subjects', ['student_id' => $studentId]);
        }
        if (($student['class_level'] ?? '') !== 'A-Level') {
            $this->respond(false, 'Combinations only apply to A-Level students.', 'students/subjects', ['student_id' => $studentId]);
        }

        $year = AcademicYear::active($schoolId);
        if (!$year) {
            $this->respond(false, 'No active academic year.', 'students/subjects');
        }
        $yearId = (int)$year['id'];
        $term   = Term::activeForSchool($schoolId);
        $termId = $term ? (int)$term['id'] : null;

        $db = Database::conn();
        $db->beginTransaction();
        try {
            StudentSubject::assignCombination(
                $studentId,
                $combinationId,
                $yearId,
                (int)Auth::user()['id'],
                trim((string)($_POST['notes'] ?? '')) ?: null
            );
            $registered = StudentSubject::registerCombination($studentId, $combinationId, $yearId, $termId);

            // Changing combination replaces its subjects; class-wide subjects stay.
            $keep = [];
            foreach (StudentSubject::combinationSubjects($combinationId) as $row) {
                $keep[] = (int)$row['subject_id'];
            }
            $classId = (int)($student['current_class_id'] ?? 0);
            if ($classId > 0) {
                foreach (StudentSubject::allocatedSubjects($classId, $yearId, $termId, (int)($student['current_stream_id'] ?? 0) ?: null) as $row) {
                    $keep[] = (int)$row['id'];
                }
            }
            foreach (StudentSubject::registrationsOf($studentId, $yearId) as $row) {
                $subjectId = (int)$row['subject_id'];
                if ($row['status'] === 'registered' && !in_array($subjectId, $keep, true)) {
                    StudentSubject::drop($studentId, $subjectId, $yearId, 'Combination changed');
                }
            }

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'allocate_combination', 'student_combinations', $studentId,
            'Assigned combination ' . $combination['code'] . ' to ' . $student['admission_number']);

        $this->respond(true, 'Combination ' . $combination['code'] . ' assigned with ' . $registered . ' subject(s) registered.', 'students/subjects', ['student_id' => $studentId]);
    }

    // ========================================================================
    // Bulk subject registration (whole class or stream at once)
    // ========================================================================
    public function bulkSave(): void
    {
        $schoolId  = school_id();
        $classId   = (int)($_POST['class_id'] ?? 0);
        $streamId  = (int)($_POST['stream_id'] ?? 0);
        $mode      = ($_POST['mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
        $year      = AcademicYear::active($schoolId);
        $term      = Term::activeForSchool($schoolId);

        $back = ['tab' => 'bulk', 'class_id' => $classId, 'stream_id' => $streamId];

        if (!$classId) {
            $this->respond(false, 'Choose a class.', 'students/subjects');
        }
        if (!$year) {
            $this->respond(false, 'No active academic year.', 'students/subjects');
        }
        if (!$this->classInSchool($classId, $schoolId)) {
            $this->respond(false, 'That class was not found in this school.', 'students/subjects');
        }

        $yearId = (int)$year['id'];
        $termId = $term ? (int)$term['id'] : null;

        $allocatedMap = [];
        foreach (StudentSubject::allocatedSubjects($classId, $yearId, $termId, $streamId ?: null) as $subject) {
            $allocatedMap[(int)$subject['id']] = (int)$subject['is_compulsory'];
        }
        if (empty($allocatedMap)) {
            $this->respond(false, 'No subjects are allocated to this class yet.', 'students/subjects', $back);
        }

        $subjectIds = array_map('intval', (array)($_POST['subjects'] ?? []));
        $subjectIds = array_values(array_intersect($subjectIds, array_keys($allocatedMap)));
        foreach ($allocatedMap as $subjectId => $isCompulsory) {
            if ($isCompulsory === 1 && !in_array($subjectId, $subjectIds, true)) {
                $subjectIds[] = $subjectId;
            }
        }
        if (empty($subjectIds)) {
            $this->respond(false, 'Choose at least one subject.', 'students/subjects', $back);
        }

        $students = StudentSubject::studentsInClass($schoolId, $classId, $streamId ?: null);
        $postedIds = array_map('intval', (array)($_POST['student_ids'] ?? []));
        if (!empty($postedIds)) {
            $students = array_values(array_filter($students, static fn(array $s): bool => in_array((int)$s['id'], $postedIds, true)));
        }
        if (empty($students)) {
            $this->respond(false, 'No active students found for the selected class or stream.', 'students/subjects', $back);
        }

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $subjectIds, $streamId ?: null);
        if (!empty($clashes)) {
            $this->respond(false, 'Timetable clashes detected: ' . implode('; ', array_column($clashes, 'message')), 'students/subjects', $back);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            $registered = 0;
            $dropped    = 0;
            foreach ($students as $student) {
                $studentId = (int)$student['id'];
                foreach ($subjectIds as $subjectId) {
                    StudentSubject::register($studentId, $subjectId, $yearId, $termId, $allocatedMap[$subjectId] === 1);
                    $registered++;
                }

                if ($mode === 'replace') {
                    foreach (StudentSubject::registrationsOf($studentId, $yearId) as $row) {
                        $subjectId = (int)$row['subject_id'];
                        if ($row['status'] === 'registered' && !in_array($subjectId, $subjectIds, true)) {
                            StudentSubject::drop($studentId, $subjectId, $yearId, 'Replaced during bulk registration');
                            $dropped++;
                        }
                    }
                }
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'bulk_register_subjects', 'student_subjects', null,
            'Bulk registered ' . count($subjectIds) . ' subject(s) for ' . count($students) . ' student(s) in class ' . $classId);

        $message = 'Registered ' . count($subjectIds) . ' subject(s) for ' . count($students)
            . ' student(s). ' . $registered . ' registration(s) saved';
        $message .= $mode === 'replace' ? ', ' . $dropped . ' removed.' : '.';

        $this->respond(true, $message, 'students/subjects', $back);
    }

    // ========================================================================
    // REST API: Register subjects for a single student
    // ========================================================================
    public function apiRegister(): void
    {
        if (!expects_json()) {
            json_out(['ok' => false, 'message' => 'Send this request as JSON (Accept: application/json or X-Requested-With: XMLHttpRequest).'], 415);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $schoolId  = school_id();
        $studentId = (int)($input['student_id'] ?? 0);
        $student   = Student::findFull($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            json_out(['ok' => false, 'message' => 'Invalid student.'], 422);
        }

        $year = AcademicYear::active($schoolId);
        if (!$year) {
            json_out(['ok' => false, 'message' => 'No active academic year.'], 422);
        }

        $yearId  = (int)$year['id'];
        $term    = Term::activeForSchool($schoolId);
        $termId  = $term ? (int)$term['id'] : null;
        $classId = (int)($student['current_class_id'] ?? 0);

        if ($classId === 0) {
            json_out(['ok' => false, 'message' => 'Student is not allocated to a class.'], 422);
        }

        $subjectIds = array_map('intval', (array)($input['subjects'] ?? []));
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'No subjects provided.'], 422);
        }

        $combination  = StudentSubject::combinationOf($studentId, $yearId);
        $allocatedMap = [];
        foreach ($this->offeredSubjects($student, $yearId, $termId, $combination) as $subject) {
            $allocatedMap[(int)$subject['id']] = (int)$subject['is_compulsory'];
        }

        $subjectIds = array_values(array_intersect($subjectIds, array_keys($allocatedMap)));
        foreach ($allocatedMap as $subjectId => $isCompulsory) {
            if ($isCompulsory === 1 && !in_array($subjectId, $subjectIds, true)) {
                $subjectIds[] = $subjectId;
            }
        }
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'No valid subjects for this class.'], 422);
        }

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $subjectIds, (int)($student['current_stream_id'] ?? 0) ?: null);
        if (!empty($clashes)) {
            json_out(['ok' => false, 'message' => 'Timetable clashes detected.', 'clashes' => $clashes], 409);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            foreach ($subjectIds as $subjectId) {
                StudentSubject::register($studentId, $subjectId, $yearId, $termId, $allocatedMap[$subjectId] === 1);
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            json_out(['ok' => false, 'message' => 'Registration failed: ' . $error->getMessage()], 500);
        }

        audit('students', 'api_register_subjects', 'student_subjects', $studentId,
            'API registered ' . count($subjectIds) . ' subject(s) for ' . $student['admission_number']);

        json_out([
            'ok'         => true,
            'message'    => count($subjectIds) . ' subject(s) registered.',
            'student_id' => $studentId,
            'subjects'   => $subjectIds,
            'registered' => count($subjectIds),
        ]);
    }

    // ========================================================================
    // REST API: Bulk register subjects for a class or stream
    // ========================================================================
    public function apiBulkRegister(): void
    {
        if (!expects_json()) {
            json_out(['ok' => false, 'message' => 'Send this request as JSON (Accept: application/json or X-Requested-With: XMLHttpRequest).'], 415);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $schoolId = school_id();
        $classId  = (int)($input['class_id'] ?? 0);
        $streamId = (int)($input['stream_id'] ?? 0);
        $mode     = ($input['mode'] ?? 'merge') === 'replace' ? 'replace' : 'merge';
        $year     = AcademicYear::active($schoolId);
        $term     = Term::activeForSchool($schoolId);

        if (!$classId) {
            json_out(['ok' => false, 'message' => 'class_id is required.'], 422);
        }
        if (!$year) {
            json_out(['ok' => false, 'message' => 'No active academic year.'], 422);
        }
        if (!$this->classInSchool($classId, $schoolId)) {
            json_out(['ok' => false, 'message' => 'That class was not found in this school.'], 422);
        }

        $yearId = (int)$year['id'];
        $termId = $term ? (int)$term['id'] : null;

        $allocatedMap = [];
        foreach (StudentSubject::allocatedSubjects($classId, $yearId, $termId, $streamId ?: null) as $subject) {
            $allocatedMap[(int)$subject['id']] = (int)$subject['is_compulsory'];
        }
        if (empty($allocatedMap)) {
            json_out(['ok' => false, 'message' => 'No subjects are allocated to this class.'], 422);
        }

        $subjectIds = array_map('intval', (array)($input['subjects'] ?? []));
        $subjectIds = array_values(array_intersect($subjectIds, array_keys($allocatedMap)));
        foreach ($allocatedMap as $subjectId => $isCompulsory) {
            if ($isCompulsory === 1 && !in_array($subjectId, $subjectIds, true)) {
                $subjectIds[] = $subjectId;
            }
        }
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'No valid subjects for this class.'], 422);
        }

        $students = StudentSubject::studentsInClass($schoolId, $classId, $streamId ?: null);
        $postedIds = array_map('intval', (array)($input['student_ids'] ?? []));
        if (!empty($postedIds)) {
            $students = array_values(array_filter($students, static fn(array $s): bool => in_array((int)$s['id'], $postedIds, true)));
        }
        if (empty($students)) {
            json_out(['ok' => false, 'message' => 'No active students found for this class or stream.'], 422);
        }

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $subjectIds, $streamId ?: null);
        if (!empty($clashes)) {
            json_out(['ok' => false, 'message' => 'Timetable clashes detected.', 'clashes' => $clashes], 409);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            $registered = 0;
            $dropped    = 0;
            foreach ($students as $student) {
                $studentId = (int)$student['id'];
                foreach ($subjectIds as $subjectId) {
                    StudentSubject::register($studentId, $subjectId, $yearId, $termId, $allocatedMap[$subjectId] === 1);
                    $registered++;
                }

                if ($mode === 'replace') {
                    foreach (StudentSubject::registrationsOf($studentId, $yearId) as $row) {
                        $subjectId = (int)$row['subject_id'];
                        if ($row['status'] === 'registered' && !in_array($subjectId, $subjectIds, true)) {
                            StudentSubject::drop($studentId, $subjectId, $yearId, 'Replaced during bulk registration');
                            $dropped++;
                        }
                    }
                }
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            json_out(['ok' => false, 'message' => 'Bulk registration failed: ' . $error->getMessage()], 500);
        }

        audit('students', 'api_bulk_register_subjects', 'student_subjects', null,
            'API bulk registered ' . count($subjectIds) . ' subject(s) for ' . count($students) . ' student(s)');

        json_out([
            'ok'         => true,
            'message'    => 'Bulk registration complete.',
            'class_id'   => $classId,
            'stream_id'  => $streamId ?: null,
            'mode'       => $mode,
            'students'   => count($students),
            'subjects'   => count($subjectIds),
            'registered' => $registered,
            'dropped'    => $dropped,
        ]);
    }

    /**
     * True when the class exists in the current school.
     */
    private function classInSchool(int $classId, int $schoolId): bool
    {
        $stmt = Database::conn()->prepare('SELECT id FROM classes WHERE id = ? AND school_id = ?');
        $stmt->execute([$classId, $schoolId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Subjects offered to a student: class allocations plus, for A-Level,
     * every subject of the assigned combination (all treated as compulsory).
     */
    private function offeredSubjects(array $student, int $yearId, ?int $termId, ?array $combination): array
    {
        $classId  = (int)($student['current_class_id'] ?? 0);
        $streamId = (int)($student['current_stream_id'] ?? 0) ?: null;

        $byId = [];
        foreach (StudentSubject::allocatedSubjects($classId, $yearId, $termId, $streamId) as $subject) {
            $id = (int)$subject['id'];
            $subject['id'] = $id;
            $subject['is_compulsory'] = (int)$subject['is_compulsory'];
            $byId[$id] = $subject;
        }

        if ($combination) {
            foreach (StudentSubject::combinationSubjects((int)$combination['combination_id']) as $subject) {
                $id = (int)$subject['subject_id'];
                if (isset($byId[$id])) {
                    $byId[$id]['is_compulsory'] = max((int)$byId[$id]['is_compulsory'], (int)$subject['is_compulsory']);
                    continue;
                }
                $byId[$id] = [
                    'id'            => $id,
                    'code'          => $subject['code'],
                    'name'          => $subject['name'],
                    'short_name'    => $subject['short_name'],
                    'level'         => $subject['level'],
                    'category'      => $subject['subject_role'],
                    'is_compulsory' => (int)$subject['is_compulsory'],
                    'order_index'   => 500,
                ];
            }
        }

        $subjects = array_values($byId);
        usort($subjects, static function (array $a, array $b): int {
            return [$a['order_index'], $a['name']] <=> [$b['order_index'], $b['name']];
        });

        return $subjects;
    }
}
