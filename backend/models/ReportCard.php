<?php
declare(strict_types=1);

/**
 * Report card data, positions and comments.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class ReportCard extends Model
{
    protected static string $table = 'subject_results';

    public static function termsForSchool(int $schoolId): array
    {
        return static::query(
            'SELECT t.id, t.name, t.term_number, t.start_date, t.end_date,
                    y.id AS year_id, y.name AS year_name
               FROM terms t
               JOIN academic_years y ON y.id = t.academic_year_id
              WHERE y.school_id = ?
              ORDER BY y.start_date DESC, t.term_number',
            [$schoolId]
        );
    }

    public static function examsForTerm(int $termId, int $schoolId): array
    {
        return static::query(
            'SELECT e.id, e.name, e.status, e.start_date AS exam_date
               FROM exams e
              WHERE e.term_id = ? AND e.school_id = ?
              ORDER BY e.id DESC',
            [$termId, $schoolId]
        );
    }

    public static function studentsForReportCard(int $classId, ?int $streamId, int $schoolId): array
    {
        $sql = "SELECT s.id, s.admission_number, s.first_name, s.last_name, s.middle_name,
                       s.gender, s.date_of_birth,
                       c.name AS class_name, c.level AS class_level,
                       st.name AS stream_name, h.name AS house_name
                  FROM students s
                  LEFT JOIN classes c ON c.id = s.current_class_id
                  LEFT JOIN streams st ON st.id = s.current_stream_id
                  LEFT JOIN houses h ON h.id = s.house_id
                 WHERE s.school_id = ? AND s.status = 'active' AND s.current_class_id = ?";
        $params = [$schoolId, $classId];
        if ($streamId !== null) {
            $sql .= ' AND s.current_stream_id = ?';
            $params[] = $streamId;
        }
        $sql .= ' ORDER BY s.last_name, s.first_name';
        return static::query($sql, $params);
    }

    public static function studentResultCard(int $studentId, int $examId, int $schoolId, bool $withPromotion = true): ?array
    {
        $student = Student::findFull($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            return null;
        }

        $exam = Examination::examInSchool($examId, $schoolId);
        if (!$exam) {
            return null;
        }

        $results = static::query(
            'SELECT sr.*, sub.code AS subject_code, sub.name AS subject_name,
                    sub.category, sub.order_index,
                    m.ca_mark, m.max_ca_mark, m.exam_mark, m.max_exam_mark,
                    m.total_mark, m.percentage AS mark_percent, m.remarks AS mark_remarks
               FROM subject_results sr
               JOIN exams e ON e.id = sr.exam_id AND e.school_id = ?
               JOIN subjects sub ON sub.id = sr.subject_id
               LEFT JOIN marks m ON m.exam_id = sr.exam_id AND m.student_id = sr.student_id
                   AND m.subject_id = sr.subject_id
              WHERE sr.exam_id = ? AND sr.student_id = ?
              ORDER BY sub.order_index, sub.name',
            [$schoolId, $examId, $studentId]
        );

        if (!$results) {
            $results = [];
        }

        $summary = static::one(
            'SELECT * FROM result_summaries WHERE exam_id = ? AND student_id = ?',
            [$examId, $studentId]
        );

        $position = null;
        $classSize = null;
        $streamPosition = null;
        $streamSize = null;
        if ($summary && $summary['average_percent'] !== null) {
            $average = (float)$summary['average_percent'];
            $classId = (int)$summary['class_id'];

            $classSize = (int)static::scalar(
                'SELECT COUNT(*) FROM result_summaries WHERE exam_id = ? AND class_id = ?',
                [$examId, $classId]
            );
            if ($classSize > 0) {
                $ahead = (int)static::scalar(
                    'SELECT COUNT(*) FROM result_summaries WHERE exam_id = ? AND class_id = ? AND average_percent > ?',
                    [$examId, $classId, $average]
                );
                $position = $ahead + 1;
            }

            if (!empty($summary['stream_id'])) {
                $streamId = (int)$summary['stream_id'];
                $streamSize = (int)static::scalar(
                    'SELECT COUNT(*) FROM result_summaries WHERE exam_id = ? AND class_id = ? AND stream_id = ?',
                    [$examId, $classId, $streamId]
                );
                if ($streamSize > 0) {
                    $streamAhead = (int)static::scalar(
                        'SELECT COUNT(*) FROM result_summaries WHERE exam_id = ? AND class_id = ? AND stream_id = ? AND average_percent > ?',
                        [$examId, $classId, $streamId, $average]
                    );
                    $streamPosition = $streamAhead + 1;
                }
            }
        }

        $guardians = Student::guardiansOf($studentId);
        $primaryGuardian = null;
        foreach ($guardians as $g) {
            if ((int)($g['is_primary'] ?? 0) === 1) {
                $primaryGuardian = $g;
                break;
            }
        }
        if ($primaryGuardian === null && $guardians) {
            $primaryGuardian = $guardians[0];
        }

        $gradeBands = Examination::scales($schoolId);

        $totalCa = 0.0;
        $totalExam = 0.0;
        $subjectsWithCa = 0;
        $subjectsWithExam = 0;
        foreach ($results as $r) {
            if ($r['ca_mark'] !== null) { $totalCa += (float)$r['ca_mark']; $subjectsWithCa++; }
            if ($r['exam_mark'] !== null) { $totalExam += (float)$r['exam_mark']; $subjectsWithExam++; }
        }

        return [
            'student' => $student,
            'exam' => $exam,
            'results' => $results,
            'summary' => $summary,
            'guardian' => $primaryGuardian,
            'grade_bands' => $gradeBands,
            'total_ca' => $totalCa,
            'total_exam' => $totalExam,
            'subjects_with_ca' => $subjectsWithCa,
            'subjects_with_exam' => $subjectsWithExam,
            'position' => $position,
            'class_size' => $classSize,
            'stream_position' => $streamPosition,
            'stream_size' => $streamSize,
            'promotion' => $withPromotion ? static::promotionAssessment($studentId, $exam, $schoolId) : null,
        ];
    }

    /**
     * Promotion rules currently in force for a school.
     */
    public static function promotionPolicy(int $schoolId): array
    {
        static $cache = [];
        if (isset($cache[$schoolId])) {
            return $cache[$schoolId];
        }
        return $cache[$schoolId] = [
            'min_average' => (float)Setting::get($schoolId, 'academic.promotion_min_average', 50),
            'min_attendance' => 75.0,
            'fee_clearance_required' => (string)Setting::get($schoolId, 'fees.fee_clearance_required', '1') === '1',
        ];
    }

    public static function promotionAssessment(int $studentId, array $exam, int $schoolId): array
    {
        $all = static::promotionAssessments([$studentId], $exam, $schoolId);
        return $all[$studentId] ?? static::emptyPromotion();
    }

    /**
     * Build promotion decisions for many students with three bulk queries.
     * Attendance is only judged after five recorded school days, so a new
     * term with light registers does not block promotion by accident.
     */
    public static function promotionAssessments(array $studentIds, array $exam, int $schoolId): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if (!$studentIds) {
            return [];
        }
        $policy = static::promotionPolicy($schoolId);
        $yearId = (int)($exam['academic_year_id'] ?? 0);
        $termId = (int)($exam['term_id'] ?? 0);
        $attendance = [];
        $balances = [];

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        if ($yearId > 0 && $termId > 0) {
            $rows = static::query(
                "SELECT student_id, COUNT(*) AS total_days,
                        SUM(CASE WHEN status IN ('present','late') THEN 1 ELSE 0 END) AS attended_days
                   FROM student_attendance
                  WHERE school_id=? AND academic_year_id=? AND term_id=? AND record_scope='daily'
                    AND student_id IN ($placeholders)
                  GROUP BY student_id",
                array_merge([$schoolId, $yearId, $termId], $studentIds)
            );
            foreach ($rows as $row) {
                $attendance[(int)$row['student_id']] = $row;
            }
            $rows = static::query(
                "SELECT student_id, COALESCE(SUM(balance),0) AS balance
                   FROM fee_invoices
                  WHERE school_id=? AND academic_year_id=? AND (term_id=? OR term_id IS NULL)
                    AND status <> 'void' AND student_id IN ($placeholders)
                  GROUP BY student_id",
                array_merge([$schoolId, $yearId, $termId], $studentIds)
            );
            foreach ($rows as $row) {
                $balances[(int)$row['student_id']] = (float)$row['balance'];
            }
        }

        $summaries = static::query(
            "SELECT student_id, average_percent FROM result_summaries
              WHERE exam_id=? AND student_id IN ($placeholders)",
            array_merge([(int)($exam['id'] ?? 0)], $studentIds)
        );
        $averages = [];
        foreach ($summaries as $row) {
            $averages[(int)$row['student_id']] = $row['average_percent'] !== null ? (float)$row['average_percent'] : 0.0;
        }

        $assessments = [];
        foreach ($studentIds as $studentId) {
            $average = $averages[$studentId] ?? 0.0;
            $attendanceRow = $attendance[$studentId] ?? null;
            $totalDays = (int)($attendanceRow['total_days'] ?? 0);
            $attendedDays = (int)($attendanceRow['attended_days'] ?? 0);
            $rate = $totalDays > 0 ? round(100 * $attendedDays / $totalDays, 1) : null;
            $balance = (float)($balances[$studentId] ?? 0);
            $status = 'Promoted';
            $reasons = [];

            if ($average < $policy['min_average']) {
                $status = 'Repeat / review';
                $reasons[] = 'Average below ' . number_format($policy['min_average'], 0) . '%';
            } elseif ($totalDays >= 5 && $rate !== null && $rate < $policy['min_attendance']) {
                $status = 'Review - attendance';
                $reasons[] = 'Attendance below ' . number_format($policy['min_attendance'], 0) . '%';
            } elseif ($policy['fee_clearance_required'] && $balance > 0) {
                $status = 'Review - fee balance';
                $reasons[] = 'Outstanding fees';
            }

            $assessments[$studentId] = [
                'status' => $status,
                'reason' => $reasons ? implode('; ', $reasons) : 'Meets promotion requirements',
                'average' => $average,
                'attendance_rate' => $rate,
                'attendance_days' => $totalDays,
                'fee_balance' => $balance,
                'min_average' => $policy['min_average'],
                'min_attendance' => $policy['min_attendance'],
                'fee_clearance_required' => $policy['fee_clearance_required'],
            ];
        }
        return $assessments;
    }

    private static function emptyPromotion(): array
    {
        return [
            'status' => 'Review required',
            'reason' => 'Results summary missing',
            'average' => 0.0,
            'attendance_rate' => null,
            'attendance_days' => 0,
            'fee_balance' => 0.0,
            'min_average' => 50.0,
            'min_attendance' => 75.0,
            'fee_clearance_required' => false,
        ];
    }

    public static function summariesForClass(int $examId, int $classId, ?int $streamId, int $schoolId): array
    {
        $sql = 'SELECT rs.*
                  FROM result_summaries rs
                  JOIN exams e ON e.id = rs.exam_id AND e.school_id = ?
                 WHERE rs.exam_id = ? AND rs.class_id = ?';
        $params = [$schoolId, $examId, $classId];
        if ($streamId !== null) {
            $sql .= ' AND rs.stream_id = ?';
            $params[] = $streamId;
        }

        $map = [];
        foreach (static::query($sql, $params) as $row) {
            $map[(int)$row['student_id']] = $row;
        }
        return $map;
    }

    public static function saveComments(
        int $studentId,
        int $examId,
        int $schoolId,
        ?string $teacherRemarks,
        ?string $headRemarks
    ): bool {
        if (!Examination::examInSchool($examId, $schoolId)) {
            return false;
        }

        $student = Student::findFull($studentId);
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            return false;
        }

        $exists = (int)static::scalar(
            'SELECT COUNT(*) FROM result_summaries WHERE exam_id = ? AND student_id = ?',
            [$examId, $studentId]
        );
        if ($exists < 1) {
            return false;
        }

        static::execute(
            'UPDATE result_summaries SET teacher_remarks = ?, head_remarks = ? WHERE exam_id = ? AND student_id = ?',
            [$teacherRemarks, $headRemarks, $examId, $studentId]
        );
        return true;
    }

    public static function classReportCardSummary(int $examId, int $schoolId): array
    {
        return static::query(
            'SELECT rs.student_id, s.admission_number, s.first_name, s.last_name,
                    rs.average_percent, rs.total_points, rs.subjects_taken, rs.subjects_passed,
                    c.name AS class_name, st.name AS stream_name
               FROM result_summaries rs
               JOIN exams e ON e.id = rs.exam_id AND e.school_id = ?
               JOIN students s ON s.id = rs.student_id
               LEFT JOIN classes c ON c.id = rs.class_id
               LEFT JOIN streams st ON st.id = rs.stream_id
              WHERE rs.exam_id = ?
              ORDER BY rs.average_percent DESC',
            [$examId, $schoolId]
        );
    }
}
