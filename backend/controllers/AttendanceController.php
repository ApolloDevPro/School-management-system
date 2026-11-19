<?php
declare(strict_types=1);

/**
 * Daily student and staff attendance capture.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class AttendanceController extends Controller
{
    private const STUDENT_STATUSES = ['present', 'absent', 'late', 'excused', 'sick', 'suspended'];
    private const STAFF_STATUSES = ['present', 'absent', 'late', 'on_leave', 'duty_off', 'sick'];

    public function index(): void
    {
        if (!$this->allowed('view')) {
            return;
        }
        $schoolId = school_id();
        $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
        if (!$this->validDate($date)) {
            $date = date('Y-m-d');
        }
        $classId = (int)($_GET['class_id'] ?? 0);
        $classes = AttendanceModule::classes($schoolId);
        if ($classId < 1 && $classes) {
            $classId = (int)$classes[0]['id'];
        }
        if ($classId && !AttendanceModule::classBelongsToSchool($classId, $schoolId)) {
            $classId = 0;
        }
        $this->render('attendance/index', [
            'pageTitle' => 'Daily attendance',
            'activeNav' => 'attendance',
            'date' => $date,
            'classId' => $classId,
            'classes' => $classes,
            'students' => $classId ? AttendanceModule::studentsForDay($schoolId, $classId, $date) : [],
            'staff' => AttendanceModule::staffForDay($schoolId, $date),
            'canAdd' => Auth::can('attendance', 'add'),
        ]);
    }

    public function recordStudents(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId = school_id();
        $classId = (int)($_POST['class_id'] ?? 0);
        $date = trim((string)($_POST['attendance_date'] ?? ''));
        $marks = $_POST['student_status'] ?? null;
        if (!$this->validDate($date) || !AttendanceModule::classBelongsToSchool($classId, $schoolId)
            || !is_array($marks) || count($marks) > 500) {
            $this->respond(false, 'Choose a valid class and date and submit the class roster.', 'attendance');
        }
        $yearTerm = AttendanceModule::activeYearAndTerm($schoolId);
        if (!$yearTerm) {
            $this->respond(false, 'Set an active academic year and term before recording attendance.', 'attendance');
        }
        $streamIds = [];
        $statement = Database::conn()->prepare(
            "SELECT id, current_stream_id FROM students
              WHERE school_id = ? AND current_class_id = ? AND status = 'active'"
        );
        $statement->execute([$schoolId, $classId]);
        foreach ($statement->fetchAll() as $row) {
            $streamIds[(int)$row['id']] = $row['current_stream_id'] ? (int)$row['current_stream_id'] : null;
        }
        $db = Database::conn();
        $write = $db->prepare(
            "INSERT INTO student_attendance
                (school_id, academic_year_id, term_id, attendance_date, record_scope,
                 class_id, stream_id, student_id, status, minutes_late, reason, recorded_by)
             VALUES (?, ?, ?, ?, 'daily', ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), minutes_late = VALUES(minutes_late),
                 reason = VALUES(reason), recorded_by = VALUES(recorded_by)"
        );
        $count = 0;
        try {
            $db->beginTransaction();
            foreach ($marks as $studentKey => $status) {
                $studentId = filter_var($studentKey, FILTER_VALIDATE_INT);
                if ($studentId === false || !array_key_exists($studentId, $streamIds)
                    || !in_array($status, self::STUDENT_STATUSES, true)) {
                    throw new InvalidArgumentException('The submitted class roster or attendance status is invalid.');
                }
                $minutes = $status === 'late' ? filter_var($_POST['minutes_late'][$studentId] ?? 0, FILTER_VALIDATE_INT) : 0;
                $reason = trim((string)($_POST['reason'][$studentId] ?? ''));
                if ($minutes === false || $minutes < 0 || $minutes > 1440 || mb_strlen($reason) > 255) {
                    throw new InvalidArgumentException('Late minutes or attendance reason is invalid.');
                }
                $write->execute([
                    $schoolId, $yearTerm['year_id'], $yearTerm['term_id'], $date, $classId,
                    $streamIds[$studentId], $studentId, $status, $minutes ?: null, $reason ?: null,
                    (int)Auth::user()['id'],
                ]);
                $count++;
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($error instanceof InvalidArgumentException) {
                $this->respond(false, $error->getMessage(), 'attendance', ['date' => $date, 'class_id' => $classId]);
            }
            throw $error;
        }
        audit('attendance', 'record', 'student_attendance', null, 'Recorded ' . $count . ' student attendance marks for ' . $date);
        $this->respond(true, 'Student attendance saved for ' . $count . ' learners.', 'attendance', ['date' => $date, 'class_id' => $classId]);
    }

    public function recordStaff(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $schoolId = school_id();
        $date = trim((string)($_POST['attendance_date'] ?? ''));
        $marks = $_POST['staff_status'] ?? null;
        if (!$this->validDate($date) || !is_array($marks) || count($marks) > 500) {
            $this->respond(false, 'Choose a valid date and submit the staff roster.', 'attendance');
        }
        $write = Database::conn()->prepare(
            'INSERT INTO staff_attendance
                (school_id, staff_id, attendance_date, status, time_in, time_out, minutes_late, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), time_in = VALUES(time_in),
                 time_out = VALUES(time_out), minutes_late = VALUES(minutes_late), recorded_by = VALUES(recorded_by)'
        );
        $db = Database::conn();
        $count = 0;
        try {
            $db->beginTransaction();
            foreach ($marks as $staffKey => $status) {
                $staffId = filter_var($staffKey, FILTER_VALIDATE_INT);
                if ($staffId === false || !AttendanceModule::staffBelongsToSchool((int)$staffId, $schoolId)
                    || !in_array($status, self::STAFF_STATUSES, true)) {
                    throw new InvalidArgumentException('The submitted staff roster or attendance status is invalid.');
                }
                $timeIn = trim((string)($_POST['time_in'][$staffId] ?? ''));
                $timeOut = trim((string)($_POST['time_out'][$staffId] ?? ''));
                $minutes = $status === 'late' ? filter_var($_POST['minutes_late'][$staffId] ?? 0, FILTER_VALIDATE_INT) : 0;
                if (($timeIn !== '' && !$this->validTime($timeIn)) || ($timeOut !== '' && !$this->validTime($timeOut))
                    || $minutes === false || $minutes < 0 || $minutes > 1440) {
                    throw new InvalidArgumentException('Enter valid staff arrival, departure and late-minute values.');
                }
                $write->execute([
                    $schoolId, $staffId, $date, $status, $timeIn ?: null, $timeOut ?: null,
                    $minutes ?: null, (int)Auth::user()['id'],
                ]);
                $count++;
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($error instanceof InvalidArgumentException) {
                $this->respond(false, $error->getMessage(), 'attendance', ['date' => $date]);
            }
            throw $error;
        }
        audit('attendance', 'record', 'staff_attendance', null, 'Recorded ' . $count . ' staff attendance marks for ' . $date);
        $this->respond(true, 'Staff attendance saved for ' . $count . ' employees.', 'attendance', ['date' => $date]);
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

    protected function allowed(string $module = 'attendance', string $action = 'view'): bool
    {
        if (Auth::can('attendance', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this attendance action.', 'dashboard');
        return false;
    }
}
