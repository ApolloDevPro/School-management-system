<?php
declare(strict_types=1);

final class StudentSubject extends Model
{
    protected static string $table = 'student_subjects';

    public static function register(int $studentId, int $subjectId, int $yearId, ?int $termId, bool $isCompulsory): void
    {
        static::execute(
            'INSERT INTO student_subjects (student_id, subject_id, academic_year_id, term_id, is_compulsory, status)
             VALUES (?, ?, ?, ?, ?, \'registered\')
             ON DUPLICATE KEY UPDATE status = \'registered\', dropped_at = NULL, dropped_reason = NULL',
            [$studentId, $subjectId, $yearId, $termId, $isCompulsory ? 1 : 0]
        );
    }

    public static function drop(int $studentId, int $subjectId, int $yearId, ?int $termId, string $reason): void
    {
        static::execute(
            "UPDATE student_subjects SET status = 'dropped', dropped_at = NOW(), dropped_reason = ?
              WHERE student_id = ? AND subject_id = ? AND academic_year_id = ? AND term_id <=> ?",
            [$reason, $studentId, $subjectId, $yearId, $termId]
        );
    }

    public static function allocatedSubjects(int $classId, int $yearId): array
    {
        return static::query(
            'SELECT sub.*, sa.is_compulsory
               FROM subject_allocations sa
               JOIN subjects sub ON sub.id = sa.subject_id
              WHERE sa.class_id = ? AND sa.academic_year_id = ? AND sa.is_active = 1
                AND (sa.stream_id IS NULL OR sa.stream_id = 0 OR sa.stream_id IN
                     (SELECT id FROM streams WHERE class_id = ?))
              GROUP BY sub.id
              ORDER BY sub.order_index, sub.name',
            [$classId, $yearId, $classId]
        );
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
            $db->prepare(
                "UPDATE student_combinations SET status = 'changed'
                  WHERE student_id = ? AND academic_year_id = ? AND status = 'active'"
            )->execute([$studentId, $yearId]);

            $db->prepare(
                'INSERT INTO student_combinations (student_id, combination_id, academic_year_id, status, allocated_by, notes)
                 VALUES (?, ?, ?, \'active\', ?, ?)'
            )->execute([$studentId, $combinationId, $yearId, $userId, $notes]);

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
            'SELECT cs.*, sub.code, sub.name, sub.short_name
               FROM combination_subjects cs
               JOIN subjects sub ON sub.id = cs.subject_id
              WHERE cs.combination_id = ?
              ORDER BY cs.order_index, sub.name',
            [$combinationId]
        );
    }

    /**
     * Check if a student's class has exam schedule clashes for given subjects.
     * Returns array of clash descriptions, empty if no clashes.
     */
    public static function checkTimetableClash(int $classId, int $yearId, ?int $termId, array $subjectIds): array
    {
        if (empty($subjectIds)) {
            return [];
        }

        $db = static::db();
        $placeholders = implode(', ', array_fill(0, count($subjectIds), '?'));

        $sql = "
            SELECT es.id, es.paper_date, es.start_time, es.end_time,
                   sub.name AS subject_name, sub.code AS subject_code,
                   e.name AS exam_name
            FROM exam_schedule es
            JOIN subjects sub ON sub.id = es.subject_id
            JOIN exams e ON e.id = es.exam_id
            WHERE es.class_id = ?
              AND es.academic_year_id = ?
              AND es.subject_id IN ($placeholders)
        ";

        $params = [$classId, $yearId, ...$subjectIds];

        if ($termId !== null) {
            $sql .= ' AND es.term_id = ?';
            $params[] = $termId;
        }

        $sql .= ' ORDER BY es.paper_date, es.start_time';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $clashes = $stmt->fetchAll();

        $results = [];
        foreach ($clashes as $clash) {
            $results[] = [
                'subject_id'    => (int)$clash['subject_id'],
                'subject_name'  => $clash['subject_name'],
                'subject_code'  => $clash['subject_code'],
                'exam_name'     => $clash['exam_name'],
                'paper_date'    => $clash['paper_date'],
                'start_time'    => $clash['start_time'],
                'end_time'      => $clash['end_time'],
                'message'       => 'Exam clash: ' . $clash['subject_name'] . ' (' . $clash['subject_code'] . ') is scheduled on '
                    . date('d M Y', strtotime($clash['paper_date'])) . ' at ' . $clash['start_time'],
            ];
        }

        return $results;
    }
}
