<?php
declare(strict_types=1);

/**
 * Student subject registrations.
 *
 * A student keeps one registration row per subject per academic year. When the
 * same subject is registered again the existing row is reused, so a subject is
 * never counted twice in reports even if the active term changed.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class StudentSubject extends Model
{
    protected static string $table = 'student_subjects';

    /**
     * Register (or re-activate) a subject for a student in the given year.
     */
    public static function register(int $studentId, int $subjectId, int $yearId, ?int $termId, bool $isCompulsory): void
    {
        $existing = static::one(
            'SELECT id FROM student_subjects
              WHERE student_id = ? AND subject_id = ? AND academic_year_id = ?
              ORDER BY (status = \'registered\') DESC, id DESC
              LIMIT 1',
            [$studentId, $subjectId, $yearId]
        );

        if ($existing) {
            static::execute(
                "UPDATE student_subjects
                    SET term_id = ?, is_compulsory = ?, status = 'registered',
                        dropped_at = NULL, dropped_reason = NULL
                  WHERE id = ?",
                [$termId, $isCompulsory ? 1 : 0, (int)$existing['id']]
            );
            return;
        }

        static::execute(
            "INSERT INTO student_subjects (student_id, subject_id, academic_year_id, term_id, is_compulsory, status)
             VALUES (?, ?, ?, ?, ?, 'registered')",
            [$studentId, $subjectId, $yearId, $termId, $isCompulsory ? 1 : 0]
        );
    }

    /**
     * Mark a subject as dropped for the whole academic year.
     */
    public static function drop(int $studentId, int $subjectId, int $yearId, string $reason): void
    {
        static::execute(
            "UPDATE student_subjects
                SET status = 'dropped', dropped_at = NOW(), dropped_reason = ?
              WHERE student_id = ? AND subject_id = ? AND academic_year_id = ? AND status = 'registered'",
            [$reason, $studentId, $subjectId, $yearId]
        );
    }

    /**
     * One row per subject for the student and year, preferring an active row.
     */
    public static function registrationsOf(int $studentId, int $yearId): array
    {
        $rows = static::query(
            'SELECT ss.*, sub.code, sub.name, sub.short_name, sub.category, sub.level
               FROM student_subjects ss
               JOIN subjects sub ON sub.id = ss.subject_id
              WHERE ss.student_id = ? AND ss.academic_year_id = ?
              ORDER BY sub.order_index, sub.name, (ss.status = \'registered\') DESC, ss.id DESC',
            [$studentId, $yearId]
        );

        $bySubject = [];
        foreach ($rows as $row) {
            $subjectId = (int)$row['subject_id'];
            if (!isset($bySubject[$subjectId])) {
                $bySubject[$subjectId] = $row;
                continue;
            }
            if ($bySubject[$subjectId]['status'] !== 'registered' && $row['status'] === 'registered') {
                $bySubject[$subjectId] = $row;
            }
        }

        return array_values($bySubject);
    }

    /**
     * Subjects offered to a class, optionally narrowed to a term and stream.
     * Class-wide allocations (no stream) always apply to every stream.
     */
    public static function allocatedSubjects(int $classId, int $yearId, ?int $termId = null, ?int $streamId = null): array
    {
        $sql = 'SELECT sub.*, MAX(sa.is_compulsory) AS is_compulsory
                  FROM subject_allocations sa
                  JOIN subjects sub ON sub.id = sa.subject_id
                 WHERE sa.class_id = ? AND sa.academic_year_id = ? AND sa.is_active = 1
                   AND sub.is_active = 1';
        $params = [$classId, $yearId];

        if ($termId !== null && $termId > 0) {
            $sql .= ' AND (sa.term_id IS NULL OR sa.term_id = ?)';
            $params[] = $termId;
        }

        // Without a stream only class-wide allocations apply; with a stream the
        // stream's own allocations are added on top of the class-wide ones.
        if ($streamId !== null && $streamId > 0) {
            $sql .= ' AND (sa.stream_id IS NULL OR sa.stream_id = 0 OR sa.stream_id = ?)';
            $params[] = $streamId;
        } else {
            $sql .= ' AND (sa.stream_id IS NULL OR sa.stream_id = 0)';
        }

        $sql .= ' GROUP BY sub.id ORDER BY sub.order_index, sub.name';

        return static::query($sql, $params);
    }

    public static function combinationOf(int $studentId, int $yearId): ?array
    {
        return static::one(
            'SELECT sc.*, c.code, c.name
               FROM student_combinations sc
               JOIN alevel_combinations c ON c.id = sc.combination_id
              WHERE sc.student_id = ? AND sc.academic_year_id = ? AND sc.status = \'active\'',
            [$studentId, $yearId]
        );
    }

    public static function assignCombination(int $studentId, int $combinationId, int $yearId, ?int $userId, ?string $notes): void
    {
        $db = static::db();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            // One combination per student per year: reassign the existing row.
            $existing = $db->prepare(
                'SELECT id FROM student_combinations WHERE student_id = ? AND academic_year_id = ? LIMIT 1'
            );
            $existing->execute([$studentId, $yearId]);
            $rowId = $existing->fetchColumn();

            if ($rowId) {
                $db->prepare(
                    "UPDATE student_combinations
                        SET combination_id = ?, status = 'active', allocated_by = ?, allocated_at = NOW(), notes = ?
                      WHERE id = ?"
                )->execute([$combinationId, $userId, $notes, (int)$rowId]);
            } else {
                $db->prepare(
                    'INSERT INTO student_combinations (student_id, combination_id, academic_year_id, status, allocated_by, notes)
                     VALUES (?, ?, ?, \'active\', ?, ?)'
                )->execute([$studentId, $combinationId, $yearId, $userId, $notes]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function combinationSubjects(int $combinationId): array
    {
        return static::query(
            'SELECT cs.*, sub.code, sub.name, sub.short_name, sub.level
               FROM combination_subjects cs
               JOIN subjects sub ON sub.id = cs.subject_id
              WHERE cs.combination_id = ?
              ORDER BY cs.order_index, sub.name',
            [$combinationId]
        );
    }

    /**
     * Register every subject of an A-Level combination as compulsory.
     * Returns the number of subjects registered.
     */
    public static function registerCombination(int $studentId, int $combinationId, int $yearId, ?int $termId): int
    {
        $registered = 0;
        foreach (static::combinationSubjects($combinationId) as $subject) {
            static::register(
                $studentId,
                (int)$subject['subject_id'],
                $yearId,
                $termId,
                (int)$subject['is_compulsory'] === 1
            );
            $registered++;
        }
        return $registered;
    }

    /**
     * Active students in a class, optionally limited to one stream.
     */
    public static function studentsInClass(int $schoolId, int $classId, ?int $streamId = null): array
    {
        $sql = 'SELECT s.id, s.admission_number, s.first_name, s.last_name,
                       s.current_class_id, s.current_stream_id,
                       c.name AS class_name, c.level AS class_level,
                       st.name AS stream_name
                  FROM students s
                  JOIN classes c ON c.id = s.current_class_id
                  LEFT JOIN streams st ON st.id = s.current_stream_id
                 WHERE s.school_id = ? AND s.current_class_id = ? AND s.status = \'active\'';
        $params = [$schoolId, $classId];

        if ($streamId !== null && $streamId > 0) {
            $sql .= ' AND s.current_stream_id = ?';
            $params[] = $streamId;
        }

        $sql .= ' ORDER BY s.last_name, s.first_name';

        return static::query($sql, $params);
    }

    /**
     * Registered subject totals keyed by student id for the given year.
     */
    public static function registrationCounts(int $schoolId, int $yearId): array
    {
        $rows = static::query(
            "SELECT ss.student_id, COUNT(DISTINCT ss.subject_id) AS registered
               FROM student_subjects ss
               JOIN students s ON s.id = ss.student_id
              WHERE s.school_id = ? AND ss.academic_year_id = ? AND ss.status = 'registered'
              GROUP BY ss.student_id",
            [$schoolId, $yearId]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['student_id']] = (int)$row['registered'];
        }
        return $counts;
    }

    /**
     * Detect real lesson clashes: two chosen subjects sitting in the same
     * timetable period for this class. Exam papers are not considered here
     * because sitting two papers on different days never blocks registration.
     */
    public static function checkTimetableClash(
        int $classId,
        int $yearId,
        ?int $termId,
        array $subjectIds,
        ?int $streamId = null
    ): array {
        $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));
        if (empty($subjectIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($subjectIds), '?'));

        $sql = "SELECT ts.day_of_week, ts.period_number,
                       MIN(ts.start_time) AS start_time,
                       MIN(ts.end_time) AS end_time,
                       GROUP_CONCAT(DISTINCT sub.name ORDER BY sub.name SEPARATOR ', ') AS subject_names,
                       GROUP_CONCAT(DISTINCT ts.subject_id) AS subject_ids
                  FROM timetable_slots ts
                  JOIN timetables t ON t.id = ts.timetable_id
                  JOIN subjects sub ON sub.id = ts.subject_id
                 WHERE ts.class_id = ? AND t.academic_year_id = ? AND t.status <> 'archived'
                   AND ts.subject_id IN ($placeholders)";
        $params = [$classId, $yearId, ...$subjectIds];

        if ($termId !== null && $termId > 0) {
            $sql .= ' AND t.term_id = ?';
            $params[] = $termId;
        }
        if ($streamId !== null && $streamId > 0) {
            $sql .= ' AND (ts.stream_id IS NULL OR ts.stream_id = 0 OR ts.stream_id = ?)';
            $params[] = $streamId;
        }

        $sql .= " GROUP BY ts.day_of_week, ts.period_number
                 HAVING COUNT(DISTINCT ts.subject_id) > 1
                 ORDER BY FIELD(ts.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), ts.period_number";

        $rows = static::query($sql, $params);

        $clashes = [];
        foreach ($rows as $row) {
            $ids = array_map('intval', explode(',', (string)$row['subject_ids']));
            $clashes[] = [
                'day'           => $row['day_of_week'],
                'period'        => (int)$row['period_number'],
                'start_time'    => $row['start_time'],
                'end_time'      => $row['end_time'],
                'subject_ids'   => $ids,
                'subject_names' => $row['subject_names'],
                'message'       => $row['day_of_week'] . ' period ' . (int)$row['period_number']
                    . ' (' . substr((string)$row['start_time'], 0, 5) . '): ' . $row['subject_names'],
            ];
        }

        return $clashes;
    }
}
