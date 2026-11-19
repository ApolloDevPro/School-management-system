<?php
declare(strict_types=1);

/**
 * Exams, marks, grading scales, result processing and summaries.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class Examination extends Model
{
    protected static string $table = 'exams';

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM academic_years WHERE school_id = ? ORDER BY start_date DESC, id DESC',
            [$schoolId]
        );
    }

    public static function terms(int $yearId, int $schoolId): array
    {
        return static::query(
            'SELECT t.* FROM terms t
              JOIN academic_years y ON y.id = t.academic_year_id
             WHERE t.academic_year_id = ? AND y.school_id = ?
             ORDER BY t.term_number',
            [$yearId, $schoolId]
        );
    }

    public static function examsForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT e.*, y.name AS year_name, t.name AS term_name
               FROM exams e
               JOIN academic_years y ON y.id = e.academic_year_id
               JOIN terms t ON t.id = e.term_id
              WHERE e.school_id = ?
              ORDER BY y.start_date DESC, t.term_number DESC, e.id DESC',
            [$schoolId]
        );
    }

    public static function examInSchool(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT e.*, y.name AS year_name, t.name AS term_name
               FROM exams e
               JOIN academic_years y ON y.id = e.academic_year_id
               JOIN terms t ON t.id = e.term_id
              WHERE e.id = ? AND e.school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function schedules(int $examId, int $schoolId): array
    {
        return static::query(
            'SELECT es.*, sub.code AS subject_code, sub.name AS subject_name,
                    c.name AS class_name, c.level AS class_level, st.name AS stream_name,
                    (SELECT COUNT(*) FROM marks m WHERE m.exam_id = es.exam_id
                       AND m.subject_id = es.subject_id AND m.class_id = es.class_id
                       AND (es.stream_id IS NULL OR m.stream_id = es.stream_id)) AS mark_count
               FROM exam_schedule es
               JOIN exams e ON e.id = es.exam_id
               JOIN subjects sub ON sub.id = es.subject_id
               JOIN classes c ON c.id = es.class_id
               LEFT JOIN streams st ON st.id = es.stream_id
              WHERE es.exam_id = ? AND e.school_id = ?
              ORDER BY es.paper_date, es.start_time, c.order_index, sub.name',
            [$examId, $schoolId]
        );
    }

    public static function scheduleInSchool(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT es.*, e.school_id, e.academic_year_id, e.term_id, e.status AS exam_status,
                    c.level AS class_level
               FROM exam_schedule es
               JOIN exams e ON e.id = es.exam_id
               JOIN classes c ON c.id = es.class_id
              WHERE es.id = ? AND e.school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function classes(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, level FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY order_index, name',
            [$schoolId]
        );
    }

    public static function subjectsForClass(int $classId, int $yearId, int $termId, int $schoolId): array
    {
        return static::query(
            'SELECT DISTINCT sub.id, sub.code, sub.name
               FROM subject_allocations sa
               JOIN subjects sub ON sub.id = sa.subject_id
               JOIN classes c ON c.id = sa.class_id
               JOIN academic_years y ON y.id = sa.academic_year_id
              WHERE sa.class_id = ? AND sa.academic_year_id = ? AND y.school_id = ?
                AND c.school_id = ? AND sub.school_id = ? AND sa.is_active = 1
                AND (sa.term_id IS NULL OR sa.term_id = ?)
              ORDER BY sub.name',
            [$classId, $yearId, $schoolId, $schoolId, $schoolId, $termId]
        );
    }

    public static function streams(int $classId): array
    {
        return static::query(
            'SELECT id, name FROM streams WHERE class_id = ? AND is_active = 1 ORDER BY name',
            [$classId]
        );
    }

    public static function studentsForSchedule(array $schedule): array
    {
        return static::query(
            "SELECT s.id, s.admission_number, s.first_name, s.last_name, s.current_stream_id,
                    m.id AS mark_id, m.ca_mark, m.max_ca_mark, m.exam_mark, m.max_exam_mark,
                    m.total_mark, m.percentage, m.status AS mark_status, m.remarks
               FROM students s
               LEFT JOIN marks m ON m.exam_id = ? AND m.student_id = s.id
                    AND m.subject_id = ? AND m.class_id = ?
                    AND (? IS NULL OR m.stream_id <=> ?)
              WHERE s.school_id = ? AND s.current_class_id = ? AND s.status = 'active'
                AND (? IS NULL OR s.current_stream_id = ?)
              ORDER BY s.last_name, s.first_name, s.id",
            [
                (int)$schedule['exam_id'], (int)$schedule['subject_id'], (int)$schedule['class_id'],
                $schedule['stream_id'], $schedule['stream_id'], (int)$schedule['school_id'], (int)$schedule['class_id'],
                $schedule['stream_id'], $schedule['stream_id'],
            ]
        );
    }

    public static function markForStudent(int $examId, int $studentId, int $subjectId): ?array
    {
        return static::one(
            'SELECT * FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ?',
            [$examId, $studentId, $subjectId]
        );
    }

    public static function scales(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM grading_scales WHERE school_id = ? AND is_active = 1
             ORDER BY level_scope, is_default DESC, name',
            [$schoolId]
        );
    }

    public static function saveMark(array $values): void
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $existing = static::one(
                'SELECT * FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ? FOR UPDATE',
                [$values['exam_id'], $values['student_id'], $values['subject_id']]
            );
            if ($existing && !in_array($existing['status'], ['draft', 'reopened'], true)) {
                throw new DomainException('Only draft or reopened marks can be edited. Reopen or return it to entry before making a correction.');
            }

            if ($existing) {
                $db->prepare(
                    'UPDATE marks
                        SET class_id = ?, stream_id = ?, teacher_id = ?, ca_mark = ?, max_ca_mark = ?,
                            exam_mark = ?, max_exam_mark = ?, total_mark = ?, percentage = ?, source = ?,
                            status = ?, remarks = ?, entered_by = ?, entered_at = NOW()
                      WHERE id = ?'
                )->execute([
                    $values['class_id'], $values['stream_id'], $values['teacher_id'],
                    $values['ca_mark'], $values['max_ca_mark'], $values['exam_mark'],
                    $values['max_exam_mark'], $values['total_mark'], $values['percentage'],
                    $values['source'], $existing['status'] === 'submitted' ? 'submitted' : 'draft',
                    $values['remarks'], $values['entered_by'], $existing['id'],
                ]);
                $values['mark_id'] = (int)$existing['id'];
                $values['action'] = 'correct';
            } else {
                $db->prepare(
                    'INSERT INTO marks
                        (exam_id, student_id, subject_id, class_id, stream_id, teacher_id, ca_mark,
                         max_ca_mark, exam_mark, max_exam_mark, total_mark, percentage, source, status,
                         remarks, entered_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', ?, ?)'
                )->execute([
                    $values['exam_id'], $values['student_id'], $values['subject_id'],
                    $values['class_id'], $values['stream_id'], $values['teacher_id'],
                    $values['ca_mark'], $values['max_ca_mark'], $values['exam_mark'],
                    $values['max_exam_mark'], $values['total_mark'], $values['percentage'],
                    $values['source'], $values['remarks'], $values['entered_by'],
                ]);
                $values['mark_id'] = (int)$db->lastInsertId();
                $values['action'] = 'correct';
            }
            self::logMark($db, (int)$values['mark_id'], $values['action'], $existing, $values, 'Mark saved.');
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function changeMarkStatus(int $markId, int $schoolId, string $action, int $actorId, string $reason): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $mark = static::one(
                'SELECT m.* FROM marks m
                   JOIN exams e ON e.id = m.exam_id
                  WHERE m.id = ? AND e.school_id = ? FOR UPDATE',
                [$markId, $schoolId]
            );
            if (!$mark) {
                throw new DomainException('Mark not found in this school.');
            }

            $transitions = [
                'submit' => ['draft' => 'submitted', 'reopened' => 'submitted'],
                'verify' => ['submitted' => 'verified'],
                'lock' => ['verified' => 'locked'],
                'unlock' => ['locked' => 'reopened'],
            ];
            if (!isset($transitions[$action][$mark['status']])) {
                throw new DomainException('That action is not valid for the current mark status.');
            }
            if (in_array($action, ['unlock'], true) && trim($reason) === '') {
                throw new DomainException('Enter a reason for reopening a locked mark.');
            }

            $newStatus = $transitions[$action][$mark['status']];
            $verifiedBy = $action === 'verify' ? $actorId : $mark['verified_by'];
            $verifiedAt = $action === 'verify' ? date('Y-m-d H:i:s') : $mark['verified_at'];
            $db->prepare('UPDATE marks SET status = ?, verified_by = ?, verified_at = ? WHERE id = ?')
                ->execute([$newStatus, $verifiedBy, $verifiedAt, $markId]);
            self::logMark($db, $markId, $action, $mark, ['status' => $newStatus], $reason);
            $db->commit();
            return ['mark' => $mark, 'status' => $newStatus];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function logMark(PDO $db, int $markId, string $action, ?array $old, array $new, string $reason): void
    {
        $db->prepare(
            'INSERT INTO marks_verification_log (mark_id, action, old_value, new_value, reason, actor_id, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $markId, $action,
            $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
            json_encode($new, JSON_UNESCAPED_UNICODE),
            mb_substr($reason, 0, 255),
            (int)(Auth::user()['id'] ?? 0) ?: null,
            mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }

    public static function processResults(int $examId, int $schoolId, array $scaleIds): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $exam = self::examInSchool($examId, $schoolId);
            if (!$exam || in_array($exam['status'], ['cancelled', 'planning', 'published'], true)) {
                throw new DomainException('Only a scheduled or active exam can be processed.');
            }

            $marks = static::query(
                "SELECT m.*, c.level AS class_level
                   FROM marks m
                   JOIN exams e ON e.id = m.exam_id
                   JOIN classes c ON c.id = m.class_id
                  WHERE m.exam_id = ? AND e.school_id = ?
                  ORDER BY m.student_id, m.subject_id",
                [$examId, $schoolId]
            );
            if (!$marks) {
                throw new DomainException('There are no marks to process.');
            }

            $scaleCache = [];
            $bandCache = [];
            $summaries = [];
            foreach ($marks as $mark) {
                if (!in_array($mark['status'], ['verified', 'locked'], true)) {
                    throw new DomainException('Verify or lock every entered mark before processing results.');
                }
                $level = $mark['class_level'];
                $scaleId = (int)($scaleIds[$level] ?? 0);
                if ($scaleId < 1) {
                    throw new DomainException('Choose a configured grading scale for ' . $level . '.');
                }
                if (!isset($scaleCache[$scaleId])) {
                    $scaleCache[$scaleId] = static::one(
                        'SELECT * FROM grading_scales
                          WHERE id = ? AND school_id = ? AND level_scope = ? AND is_active = 1',
                        [$scaleId, $schoolId, $level]
                    );
                    if (!$scaleCache[$scaleId]) {
                        throw new DomainException('The selected grading scale is not active or does not match the class level.');
                    }
                    $bandCache[$scaleId] = static::query(
                        'SELECT * FROM grade_bands WHERE grading_scale_id = ? ORDER BY band_order, min_percent DESC',
                        [$scaleId]
                    );
                    if (!$bandCache[$scaleId]) {
                        throw new DomainException('The selected grading scale has no configured grade bands.');
                    }
                }

                $percent = (float)$mark['percentage'];
                $band = null;
                foreach ($bandCache[$scaleId] as $candidate) {
                    if ($percent >= (float)$candidate['min_percent'] && $percent <= (float)$candidate['max_percent']) {
                        $band = $candidate;
                        break;
                    }
                }
                if (!$band) {
                    throw new DomainException('No configured grade band covers ' . number_format($percent, 2) . '%. Update the grading scale.');
                }

                $db->prepare(
                    'INSERT INTO subject_results
                        (exam_id, student_id, subject_id, class_id, stream_id, weighted_percent,
                         grading_scale_id, grade_code, grade_name, points, is_provisional, computed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                     ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), stream_id = VALUES(stream_id),
                         weighted_percent = VALUES(weighted_percent), grading_scale_id = VALUES(grading_scale_id),
                         grade_code = VALUES(grade_code), grade_name = VALUES(grade_name),
                         points = VALUES(points), is_provisional = 1, computed_at = NOW()'
                )->execute([
                    $examId, $mark['student_id'], $mark['subject_id'], $mark['class_id'],
                    $mark['stream_id'], $percent, $scaleId, $band['grade_code'], $band['grade_name'], $band['points'],
                ]);

                $key = (int)$mark['student_id'];
                if (!isset($summaries[$key])) {
                    $summaries[$key] = [
                        'class_id' => (int)$mark['class_id'],
                        'stream_id' => $mark['stream_id'],
                        'count' => 0,
                        'passed' => 0,
                        'sum' => 0.0,
                        'points' => 0.0,
                        'has_points' => false,
                    ];
                }
                $summaries[$key]['count']++;
                $summaries[$key]['passed'] += (int)$band['is_pass'];
                $summaries[$key]['sum'] += $percent;
                if ($band['points'] !== null) {
                    $summaries[$key]['points'] += (float)$band['points'];
                    $summaries[$key]['has_points'] = true;
                }
            }

            foreach ($summaries as $studentId => $summary) {
                $average = round($summary['sum'] / $summary['count'], 2);
                $totalPoints = $summary['has_points'] ? round($summary['points'], 2) : null;
                $db->prepare(
                    'INSERT INTO result_summaries
                        (exam_id, student_id, class_id, stream_id, subjects_taken, subjects_passed,
                         average_percent, total_points, computed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), stream_id = VALUES(stream_id),
                         subjects_taken = VALUES(subjects_taken), subjects_passed = VALUES(subjects_passed),
                         average_percent = VALUES(average_percent), total_points = VALUES(total_points),
                         computed_at = NOW()'
                )->execute([
                    $examId, $studentId, $summary['class_id'], $summary['stream_id'],
                    $summary['count'], $summary['passed'], $average, $totalPoints,
                ]);
            }

            $db->prepare("UPDATE exams SET status = 'results_ready' WHERE id = ? AND school_id = ?")
                ->execute([$examId, $schoolId]);
            $db->commit();
            return ['marks' => count($marks), 'students' => count($summaries)];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    // ========================================================================
    // Students with marks for a schedule (mark entry grid)
    // ========================================================================
    public static function studentsForScheduleWithMarks(array $schedule): array
    {
        $schoolId = $schedule['school_id'] ?? 0;
        $examId = (int)$schedule['exam_id'];
        $subjectId = (int)$schedule['subject_id'];
        $classId = (int)$schedule['class_id'];
        $streamId = $schedule['stream_id'] ?? null;

        $sql = "
            SELECT s.id AS student_id, s.admission_number, s.first_name, s.last_name,
                   s.current_stream_id,
                   m.id AS mark_id, m.ca_mark, m.max_ca_mark, m.exam_mark, m.max_exam_mark,
                   m.total_mark, m.percentage, m.status AS mark_status, m.remarks,
                   sch.max_mark AS schedule_max_mark
            FROM students s
            LEFT JOIN marks m ON m.student_id = s.id
                AND m.exam_id = ? AND m.subject_id = ? AND m.class_id = ?
            WHERE s.school_id = ? AND s.current_class_id = ? AND s.status = 'active'
              AND (? IS NULL OR s.current_stream_id = ?)
            ORDER BY s.last_name, s.first_name
        ";

        return static::query($sql, [
            $examId, $subjectId, $classId,
            $schoolId, $classId, $streamId, $streamId,
        ]);
    }

    // ========================================================================
    // Timetable clash detection for schedules
    // ========================================================================
    public static function checkScheduleClash(
        int $classId,
        ?int $streamId,
        string $paperDate,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null
    ): array
    {
        $sql = "
            SELECT es.id, es.paper_date, es.start_time, es.end_time,
                   sub.code AS subject_code, sub.name AS subject_name,
                   c.name AS class_name
            FROM exam_schedule es
            JOIN subjects sub ON sub.id = es.subject_id
            JOIN classes c ON c.id = es.class_id
            WHERE es.class_id = ?
              AND es.paper_date = ?
              AND (
                    (es.start_time < ? AND es.end_time > ?)
                 OR (es.start_time >= ? AND es.start_time < ?)
                 OR (? >= es.start_time AND ? < es.end_time)
                  )
        ";

        $params = [$classId, $paperDate, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime];

        if ($streamId !== null) {
            $sql .= ' AND es.stream_id = ?';
            $params[] = $streamId;
        } else {
            $sql .= ' AND (es.stream_id IS NULL OR es.stream_id = 0)';
        }

        if ($ignoreScheduleId !== null) {
            $sql .= ' AND es.id <> ?';
            $params[] = $ignoreScheduleId;
        }
        $sql .= ' ORDER BY es.start_time';

        return static::query($sql, $params);
    }

    // ========================================================================
    // Check if all marks are entered for an exam
    // ========================================================================
    public static function allMarksEntered(int $examId, int $schoolId): array
    {
        $result = ['complete' => true, 'missing' => [], 'total' => 0, 'entered' => 0];

        $schedules = static::query(
            'SELECT es.id, es.subject_id, es.class_id, es.stream_id,
                    sub.code AS subject_code, sub.name AS subject_name,
                    c.name AS class_name
               FROM exam_schedule es
               JOIN subjects sub ON sub.id = es.subject_id
               JOIN classes c ON c.id = es.class_id
               JOIN exams e ON e.id = es.exam_id
              WHERE es.exam_id = ? AND e.school_id = ?
              ORDER BY c.name, sub.name',
            [$examId, $schoolId]
        );

        foreach ($schedules as $schedule) {
            $students = static::query(
                'SELECT s.id FROM students s
                  WHERE s.school_id = ? AND s.current_class_id = ? AND s.status = \'active\'
                    AND (? IS NULL OR s.current_stream_id = ?)',
                [$schoolId, (int)$schedule['class_id'], $schedule['stream_id'], $schedule['stream_id']]
            );

            $result['total'] += count($students);

            foreach ($students as $student) {
                $mark = static::one(
                    'SELECT id FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ?',
                    [$examId, (int)$student['id'], (int)$schedule['subject_id']]
                );
                if (!$mark) {
                    $result['complete'] = false;
                    $result['missing'][] = [
                        'student_id' => (int)$student['id'],
                        'subject_id' => (int)$schedule['subject_id'],
                        'subject_name' => $schedule['subject_name'],
                        'class_name' => $schedule['class_name'],
                    ];
                } else {
                    $result['entered']++;
                }
            }
        }

        return $result;
    }

    // ========================================================================
    // Class statistics for an exam
    // ========================================================================
    public static function classStatistics(int $examId, int $schoolId, int $classId, ?int $streamId = null): array
    {
        $stats = [
            'subjects' => [],
            'class_average' => 0.0,
            'pass_rate' => 0.0,
            'total_students' => 0,
            'top_student' => null,
        ];

        $schedules = static::query(
            'SELECT es.id, es.subject_id, sub.code AS subject_code, sub.name AS subject_name
               FROM exam_schedule es
               JOIN subjects sub ON sub.id = es.subject_id
               JOIN exams e ON e.id = es.exam_id
              WHERE es.exam_id = ? AND e.school_id = ? AND es.class_id = ?
                AND (? IS NULL OR es.stream_id = ? OR es.stream_id IS NULL)
              ORDER BY sub.name',
            [$examId, $schoolId, $classId, $streamId, $streamId]
        );

        $students = static::query(
            'SELECT id, admission_number, first_name, last_name
               FROM students
              WHERE school_id = ? AND current_class_id = ? AND status = \'active\'
                AND (? IS NULL OR current_stream_id = ?)
              ORDER BY last_name, first_name',
            [$schoolId, $classId, $streamId, $streamId]
        );

        $stats['total_students'] = count($students);

        foreach ($schedules as $schedule) {
            $subjectStats = [
                'subject_id' => (int)$schedule['subject_id'],
                'subject_code' => $schedule['subject_code'],
                'subject_name' => $schedule['subject_name'],
                'average' => 0.0,
                'pass_rate' => 0.0,
                'highest' => 0.0,
                'lowest' => 100.0,
                'count' => 0,
            ];

            $sum = 0.0;
            $passed = 0;
            $highest = 0.0;
            $lowest = 100.0;
            $count = 0;

            foreach ($students as $student) {
                $mark = static::one(
                    'SELECT percentage FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ?',
                    [$examId, (int)$student['id'], (int)$schedule['subject_id']]
                );
                if ($mark) {
                    $pct = (float)$mark['percentage'];
                    $sum += $pct;
                    $count++;
                    if ($pct > $highest) $highest = $pct;
                    if ($pct < $lowest) $lowest = $pct;
                    if ($pct >= 50) $passed++;
                }
            }

            if ($count > 0) {
                $subjectStats['average'] = round($sum / $count, 2);
                $subjectStats['pass_rate'] = round(($passed / $count) * 100, 2);
                $subjectStats['highest'] = round($highest, 2);
                $subjectStats['lowest'] = round($lowest, 2);
                $subjectStats['count'] = $count;
            }

            $stats['subjects'][] = $subjectStats;
        }

        if (count($stats['subjects']) > 0) {
            $allAvgs = array_column($stats['subjects'], 'average');
            $allAvgs = array_filter($allAvgs, fn($v) => $v > 0);
            if (!empty($allAvgs)) {
                $stats['class_average'] = round(array_sum($allAvgs) / count($allAvgs), 2);
            }
            $totalPassed = array_sum(array_column($stats['subjects'], 'pass_rate'));
            $stats['pass_rate'] = count($stats['subjects']) > 0 ? round($totalPassed / count($stats['subjects']), 2) : 0.0;
        }

        return $stats;
    }

    // ========================================================================
    // Get results for a student in an exam
    // ========================================================================
    public static function getResultsForStudent(int $examId, int $studentId, int $schoolId): ?array
    {
        $student = static::one(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        );
        if (!$student) {
            return null;
        }

        $exam = static::examInSchool($examId, $schoolId);
        if (!$exam) {
            return null;
        }

        $results = static::query(
            'SELECT sr.*, sub.code AS subject_code, sub.name AS subject_name,
                    m.ca_mark, m.exam_mark, m.total_mark, m.percentage, m.status AS mark_status
               FROM subject_results sr
               JOIN subjects sub ON sub.id = sr.subject_id
               LEFT JOIN marks m ON m.exam_id = sr.exam_id AND m.student_id = sr.student_id
                   AND m.subject_id = sr.subject_id
              WHERE sr.exam_id = ? AND sr.student_id = ?
              ORDER BY sr.weighted_percent DESC',
            [$examId, $studentId]
        );

        return [
            'student' => $student,
            'exam' => $exam,
            'results' => $results,
        ];
    }

    // ========================================================================
    // Bulk save marks for multiple students
    // ========================================================================
    public static function bulkSaveMarks(
        int $examId,
        int $scheduleId,
        array $marks,
        int $actorId
    ): array
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $schedule = static::scheduleInSchool($scheduleId, 0);
            if (!$schedule) {
                throw new DomainException('Schedule not found.');
            }
            $schoolId = (int)$schedule['school_id'] ?? 0;
            $subjectId = (int)$schedule['subject_id'];
            $classId = (int)$schedule['class_id'];
            $streamId = $schedule['stream_id'] ?? null;
            $maxExamMark = (float)$schedule['max_mark'];

            $inserted = 0;
            $updated = 0;
            $errors = [];
            foreach ($marks as $index => $mark) {
                $studentId = (int)($mark['student_id'] ?? 0);
                $examRaw = $mark['exam_mark'] ?? '';
                $caRaw = $mark['ca_mark'] ?? '';
                $caMaxRaw = $mark['max_ca_mark'] ?? '';
                $remarks = mb_substr(trim((string)($mark['remarks'] ?? '')), 0, 255) ?: null;

                $examMark = $mark['exam_mark'] !== '' ? (float)$mark['exam_mark'] : null;
                $caMark = $mark['ca_mark'] !== '' ? (float)$mark['ca_mark'] : null;
                $caMax = $mark['max_ca_mark'] !== '' ? (float)$mark['max_ca_mark'] : null;

                if ($examMark === null || $examMark < 0 || $examMark > $maxExamMark) {
                    $errors[] = "Row $index: Invalid exam mark for student $studentId.";
                    continue;
                }

                if (($caRaw !== '' && $caMark === null) || ($caMaxRaw !== '' && $caMax === null)) {
                    $errors[] = "Row $index: Enter both CA score and maximum for student $studentId.";
                    continue;
                }

                if (($caMark === null) !== ($caMax === null)) {
                    $errors[] = "Row $index: CA score and maximum must be provided together for student $studentId.";
                    continue;
                }

                if ($caMark !== null && ($caMark < 0 || $caMax <= 0 || $caMark > $caMax)) {
                    $errors[] = "Row $index: Invalid CA score/maximum for student $studentId.";
                    continue;
                }

                $total = $examMark + ($caMark ?? 0);
                $maximum = $maxExamMark + ($caMax ?? 0);
                $percentage = round(($total / $maximum) * 100, 2);

                $student = static::one(
                    'SELECT id FROM students WHERE id = ? AND school_id = ? AND current_class_id = ? AND status = \'active\'
                      AND (? IS NULL OR current_stream_id = ?)',
                    [$studentId, $schoolId, $classId, $streamId, $streamId]
                );
                if (!$student) {
                    $errors[] = "Row $index: Student $studentId not found in class/school.";
                    continue;
                }

                $existing = static::one(
                    'SELECT id, status FROM marks WHERE exam_id = ? AND student_id = ? AND subject_id = ?',
                    [$examId, $studentId, $subjectId]
                );

                if ($existing && !in_array($existing['status'], ['draft', 'reopened'], true)) {
                    $errors[] = "Row $index: Mark for student $studentId is not editable (status: {$existing['status']}).";
                    continue;
                }

                $values = [
                    'exam_id' => $examId,
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'class_id' => $classId,
                    'stream_id' => $streamId,
                    'teacher_id' => $actorId,
                    'ca_mark' => $caMark,
                    'max_ca_mark' => $caMax,
                    'exam_mark' => $examMark,
                    'max_exam_mark' => $maxExamMark,
                    'total_mark' => round($total, 2),
                    'percentage' => $percentage,
                    'source' => 'bulk_import',
                    'remarks' => $remarks,
                    'entered_by' => $actorId,
                ];

                if ($existing) {
                    static::db()->prepare(
                        'UPDATE marks
                            SET class_id = ?, stream_id = ?, teacher_id = ?, ca_mark = ?, max_ca_mark = ?,
                                exam_mark = ?, max_exam_mark = ?, total_mark = ?, percentage = ?, source = ?,
                                status = ?, remarks = ?, entered_by = ?, entered_at = NOW()
                          WHERE id = ?'
                    )->execute([
                        $classId, $streamId, $actorId, $caMark, $caMax,
                        $examMark, $maxExamMark, round($total, 2), $percentage, 'bulk_import',
                        $existing['status'] === 'submitted' ? 'submitted' : 'draft',
                        $remarks, $actorId, $existing['id'],
                    ]);
                    $updated++;
                } else {
                    static::db()->prepare(
                        'INSERT INTO marks
                            (exam_id, student_id, subject_id, class_id, stream_id, teacher_id, ca_mark,
                             max_ca_mark, exam_mark, max_exam_mark, total_mark, percentage, source, status,
                             remarks, entered_by)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'draft\', ?, ?)'
                    )->execute([
                        $examId, $studentId, $subjectId,
                        $classId, $streamId, $actorId, $caMark, $caMax,
                        $examMark, $maxExamMark, round($total, 2), $percentage, 'bulk_import',
                        $remarks, $actorId,
                    ]);
                    $inserted++;
                }
            }

            $db->commit();
            return ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
