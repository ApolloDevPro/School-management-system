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
        $studentId = (int)($_GET['student_id'] ?? 0);
        $student   = $studentId > 0 ? Student::findFull($studentId) : null;
        if ($student && (int)$student['school_id'] !== $schoolId) {
            $student = null;
        }

        $year = AcademicYear::active($schoolId);
        $yearId = $year ? (int)$year['id'] : 0;

        $allocated = [];
        $registered = [];
        $combination = null;
        $combinations = [];

        if ($student && $yearId > 0) {
            $allocated  = $student['current_class_id']
                ? StudentSubject::allocatedSubjects((int)$student['current_class_id'], $yearId)
                : [];
            $registered = Student::subjectsOf($studentId, $yearId);
            $combination = StudentSubject::combinationOf($studentId, $yearId);
            $combinations = AlevelCombination::activeForSchool($schoolId);
        }

        $registeredMap = [];
        foreach ($registered as $row) {
            $registeredMap[(int)$row['subject_id']] = $row;
        }

        $this->render('students/subjects', [
            'pageTitle'    => 'Subject registration',
            'activeNav'    => 'students_subjects',
            'student'      => $student,
            'year'         => $year,
            'allocated'    => $allocated,
            'registeredMap'=> $registeredMap,
            'combination'  => $combination,
            'combinations' => $combinations,
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

        $term = Term::active();
        $yearId  = (int)$year['id'];
        $termId  = $term ? (int)$term['id'] : null;
        $classId = (int)($student['current_class_id'] ?? 0);

        if ($classId === 0) {
            $this->respond(false, 'Allocate the student to a class before registering subjects.', 'students/profile', ['id' => $studentId]);
        }

        $allowed = [];
        foreach (StudentSubject::allocatedSubjects($classId, $yearId) as $subject) {
            $allowed[(int)$subject['id']] = (int)$subject['is_compulsory'];
        }

        $chosen = array_map('intval', (array)($_POST['subjects'] ?? []));
        $chosen = array_values(array_intersect($chosen, array_keys($allowed)));

        // Timetable clash validation
        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $chosen);
        if (!empty($clashes)) {
            $clashMessages = array_column($clashes, 'message');
            $this->respond(false, 'Timetable clashes detected: ' . implode('; ', $clashMessages), 'students/subjects', ['student_id' => $studentId]);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            $current = Student::subjectsOf($studentId, $yearId);
            $currentMap = [];
            foreach ($current as $row) {
                $currentMap[(int)$row['subject_id']] = $row;
            }

            foreach ($chosen as $subjectId) {
                StudentSubject::register($studentId, $subjectId, $yearId, $termId, (bool)$allowed[$subjectId]);
            }

            foreach ($currentMap as $subjectId => $row) {
                if (!in_array($subjectId, $chosen, true) && $row['status'] !== 'dropped') {
                    StudentSubject::drop($studentId, $subjectId, $yearId, $termId, 'Removed during registration');
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
        $student   = Student::find($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            $this->respond(false, 'Choose a valid student.', 'students/subjects');
        }

        $combinationId = (int)($_POST['combination_id'] ?? 0);
        $combination   = $combinationId > 0 ? AlevelCombination::findInSchool($combinationId, $schoolId) : null;
        if (!$combination) {
            $this->respond(false, 'Choose a valid combination.', 'students/subjects', ['student_id' => $studentId]);
        }

        $year = AcademicYear::active($schoolId);
        if (!$year) {
            $this->respond(false, 'No active academic year.', 'students/subjects');
        }

        StudentSubject::assignCombination(
            $studentId,
            $combinationId,
            (int)$year['id'],
            (int)Auth::user()['id'],
            trim((string)($_POST['notes'] ?? '')) ?: null
        );

        audit('students', 'allocate_combination', 'student_combinations', $studentId,
            'Assigned combination ' . $combination['code'] . ' to ' . $student['admission_number']);

        $this->respond(true, 'Combination ' . $combination['code'] . ' assigned.', 'students/subjects', ['student_id' => $studentId]);
    }

    // ========================================================================
    // Bulk Subject Registration (register multiple students at once)
    // ========================================================================
    public function bulkSave(): void
    {
        $schoolId  = school_id();
        $classId   = (int)($_POST['class_id'] ?? 0);
        $year      = AcademicYear::active($schoolId);
        $term      = Term::active();

        if (!$classId) {
            $this->respond(false, 'Choose a class.', 'students/subjects');
        }
        if (!$year) {
            $this->respond(false, 'No active academic year.', 'students/subjects');
        }

        $yearId  = (int)$year['id'];
        $termId  = $term ? (int)$term['id'] : null;

        $studentIds = array_map('intval', (array)($_POST['student_ids'] ?? []));
        $subjectIds = array_map('intval', (array)($_POST['subjects'] ?? []));

        if (empty($studentIds)) {
            $this->respond(false, 'Choose at least one student.', 'students/subjects');
        }
        if (empty($subjectIds)) {
            $this->respond(false, 'Choose at least one subject.', 'students/subjects');
        }

        $db = Database::conn();
        $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
        $stmt = $db->prepare(
            "SELECT id, admission_number, current_class_id FROM students
             WHERE id IN ($placeholders) AND school_id = ? AND status = 'active'"
        );
        $stmt->execute([...$studentIds, $schoolId]);
        $students = $stmt->fetchAll();

        if (count($students) !== count($studentIds)) {
            $this->respond(false, 'Some students are invalid or not in this school.', 'students/subjects');
        }

        $eligible = [];
        foreach ($students as $s) {
            if ((int)$s['current_class_id'] === $classId) {
                $eligible[] = $s;
            }
        }

        if (empty($eligible)) {
            $this->respond(false, 'No active students found in this class.', 'students/subjects');
        }

        $allocated = StudentSubject::allocatedSubjects($classId, $yearId);
        $compulsoryIds = [];
        foreach ($allocated as $subj) {
            if ((int)$subj['is_compulsory'] === 1) {
                $compulsoryIds[] = (int)$subj['id'];
            }
        }

        $subjectIds = array_values(array_unique(array_merge($subjectIds, $compulsoryIds)));

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $subjectIds);

        $db->beginTransaction();
        try {
            $registered = 0;
            foreach ($eligible as $student) {
                $studentId = (int)$student['id'];
                foreach ($subjectIds as $subjectId) {
                    $isCompulsory = in_array($subjectId, $compulsoryIds, true);
                    StudentSubject::register($studentId, $subjectId, $yearId, $termId, $isCompulsory);
                    $registered++;
                }
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'bulk_register_subjects', 'student_subjects', null,
            'Bulk registered ' . count($subjectIds) . ' subject(s) for ' . count($eligible) . ' student(s) in class ' . $classId);

        $msg = 'Bulk registration complete: ' . count($eligible) . ' student(s), '
            . count($subjectIds) . ' subject(s) each. ' . $registered . ' registration(s) created.';
        if (!empty($clashes)) {
            $msg .= ' Timetable clashes detected for: ' . implode(', ', array_column($clashes, 'subject_name'));
        }

        $this->respond(true, $msg, 'students/subjects');
    }

    // ========================================================================
    // REST API: Register subjects for a single student
    // ========================================================================
    public function apiRegister(): void
    {
        if (!expects_json()) {
            http_response_code(415);
            json_out(['ok' => false, 'message' => 'JSON content type required.'], 415);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $schoolId  = school_id();
        $studentId = (int)($input['student_id'] ?? 0);
        $student   = Student::find($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            json_out(['ok' => false, 'message' => 'Invalid student.'], 422);
        }

        $year = AcademicYear::active($schoolId);
        if (!$year) {
            json_out(['ok' => false, 'message' => 'No active academic year.'], 422);
        }

        $yearId  = (int)$year['id'];
        $term    = Term::active();
        $termId  = $term ? (int)$term['id'] : null;
        $classId = (int)($student['current_class_id'] ?? 0);

        if ($classId === 0) {
            json_out(['ok' => false, 'message' => 'Student is not allocated to a class.'], 422);
        }

        $subjectIds = array_map('intval', (array)($input['subjects'] ?? []));
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'No subjects provided.'], 422);
        }

        $allocated = StudentSubject::allocatedSubjects($classId, $yearId);
        $allocatedIds = [];
        foreach ($allocated as $subj) {
            $allocatedIds[] = (int)$subj['id'];
        }

        $subjectIds = array_values(array_intersect($subjectIds, $allocatedIds));
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'No valid subjects for this class.'], 422);
        }

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $subjectIds);
        if (!empty($clashes)) {
            json_out([
                'ok'    => false,
                'message' => 'Timetable clashes detected.',
                'clashes' => $clashes,
            ], 409);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            $registered = 0;
            foreach ($subjectIds as $subjectId) {
                $isCompulsory = in_array($subjectId, $allocatedIds, true);
                StudentSubject::register($studentId, $subjectId, $yearId, $termId, (bool)$isCompulsory);
                $registered++;
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
            'registered' => $registered,
        ]);
    }

    // ========================================================================
    // REST API: Bulk register subjects for multiple students
    // ========================================================================
    public function apiBulkRegister(): void
    {
        if (!expects_json()) {
            http_response_code(415);
            json_out(['ok' => false, 'message' => 'JSON content type required.'], 415);
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $schoolId = school_id();
        $classId  = (int)($input['class_id'] ?? 0);
        $year     = AcademicYear::active($schoolId);
        $term     = Term::active();

        if (!$classId) {
            json_out(['ok' => false, 'message' => 'class_id is required.'], 422);
        }
        if (!$year) {
            json_out(['ok' => false, 'message' => 'No active academic year.'], 422);
        }

        $yearId  = (int)$year['id'];
        $termId  = $term ? (int)$term['id'] : null;

        $studentIds = array_map('intval', (array)($input['student_ids'] ?? []));
        $subjectIds = array_map('intval', (array)($input['subjects'] ?? []));

        if (empty($studentIds)) {
            json_out(['ok' => false, 'message' => 'student_ids is required.'], 422);
        }
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'subjects is required.'], 422);
        }

        $placeholders = implode(', ', array_fill(0, count($studentIds), '?'));
        $stmt = Database::conn()->prepare(
            "SELECT id, admission_number, current_class_id FROM students
             WHERE id IN ($placeholders) AND school_id = ? AND status = 'active'"
        );
        $stmt->execute([...$studentIds, $schoolId]);
        $students = $stmt->fetchAll();

        if (count($students) !== count($studentIds)) {
            json_out(['ok' => false, 'message' => 'Some students are invalid.'], 422);
        }

        $eligible = [];
        foreach ($students as $s) {
            if ((int)$s['current_class_id'] === $classId) {
                $eligible[] = $s;
            }
        }

        if (empty($eligible)) {
            json_out(['ok' => false, 'message' => 'No eligible students in this class.'], 422);
        }

        $allocated = StudentSubject::allocatedSubjects($classId, $yearId);
        $allocatedIds = [];
        foreach ($allocated as $subj) {
            $allocatedIds[] = (int)$subj['id'];
        }

        $subjectIds = array_values(array_intersect($subjectIds, $allocatedIds));
        if (empty($subjectIds)) {
            json_out(['ok' => false, 'message' => 'No valid subjects for this class.'], 422);
        }

        $clashes = StudentSubject::checkTimetableClash($classId, $yearId, $termId, $subjectIds);
        if (!empty($clashes)) {
            json_out([
                'ok'    => false,
                'message' => 'Timetable clashes detected.',
                'clashes' => $clashes,
            ], 409);
        }

        $db = Database::conn();
        $db->beginTransaction();
        try {
            $registered = 0;
            foreach ($eligible as $student) {
                foreach ($subjectIds as $subjectId) {
                    $isCompulsory = in_array($subjectId, $allocatedIds, true);
                    StudentSubject::register($student['id'], $subjectId, $yearId, $termId, $isCompulsory);
                    $registered++;
                }
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            json_out(['ok' => false, 'message' => 'Bulk registration failed: ' . $error->getMessage()], 500);
        }

        audit('students', 'api_bulk_register_subjects', 'student_subjects', null,
            'API bulk registered ' . count($subjectIds) . ' subject(s) for ' . count($eligible) . ' student(s)');

        json_out([
            'ok'         => true,
            'message'    => 'Bulk registration complete.',
            'class_id'   => $classId,
            'students'   => count($eligible),
            'subjects'   => count($subjectIds),
            'registered' => $registered,
        ]);
    }
}
