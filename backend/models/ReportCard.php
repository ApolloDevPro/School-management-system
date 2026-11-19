<?php
declare(strict_types=1);

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

    public static function studentResultCard(int $studentId, int $examId, int $schoolId): ?array
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
