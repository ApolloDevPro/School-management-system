<?php
declare(strict_types=1);

final class Student extends Model
{
    protected static string $table = 'students';

    public static function listForSchool(int $schoolId, array $filters = []): array
    {
        $sql = 'SELECT s.*,
                       c.name AS class_name, c.level AS class_level,
                       st.name AS stream_name,
                       h.name AS house_name
                  FROM students s
                  LEFT JOIN classes c ON c.id = s.current_class_id
                  LEFT JOIN streams st ON st.id = s.current_stream_id
                  LEFT JOIN houses h ON h.id = s.house_id
                 WHERE s.school_id = ?';
        $params = [$schoolId];

        $status = $filters['status'] ?? '';
        if ($status !== '') {
            $sql .= ' AND s.status = ?';
            $params[] = $status;
        } else {
            $sql .= " AND s.status IN ('active','applicant','suspended')";
        }

        $classId = (int)($filters['class_id'] ?? 0);
        if ($classId > 0) {
            $sql .= ' AND s.current_class_id = ?';
            $params[] = $classId;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_number LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }

        $sql .= ' ORDER BY s.last_name, s.first_name';
        return static::query($sql, $params);
    }

    public static function findFull(int $id): ?array
    {
        return static::one(
            'SELECT s.*,
                    c.name AS class_name, c.level AS class_level,
                    st.name AS stream_name,
                    h.name AS house_name,
                    y.name AS year_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
               LEFT JOIN houses h ON h.id = s.house_id
               LEFT JOIN academic_years y ON y.id = s.academic_year_id
              WHERE s.id = ?',
            [$id]
        );
    }

    public static function activeCount(int $schoolId): int
    {
        return (int)static::scalar(
            "SELECT COUNT(*) FROM students WHERE school_id = ? AND status = 'active'",
            [$schoolId]
        );
    }

    public static function admissionTaken(int $schoolId, string $admissionNumber, ?int $ignoreId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM students WHERE school_id = ? AND admission_number = ?';
        $params = [$schoolId, $admissionNumber];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function guardiansOf(int $studentId): array
    {
        return static::query(
            'SELECT g.*, sg.relationship AS link_relationship, sg.is_primary AS link_is_primary,
                    sg.lives_with, sg.is_fee_payer
               FROM student_guardians sg
               JOIN guardians g ON g.id = sg.guardian_id
              WHERE sg.student_id = ?
              ORDER BY sg.is_primary DESC, g.last_name',
            [$studentId]
        );
    }

    public static function subjectsOf(int $studentId, int $yearId): array
    {
        return static::query(
            'SELECT ss.*, sub.code, sub.name, sub.short_name, sub.category, sub.level
               FROM student_subjects ss
               JOIN subjects sub ON sub.id = ss.subject_id
              WHERE ss.student_id = ? AND ss.academic_year_id = ?
              ORDER BY sub.order_index, sub.name',
            [$studentId, $yearId]
        );
    }

    public static function historyOf(int $studentId): array
    {
        return static::query(
            'SELECT h.*, c.name AS class_name, st.name AS stream_name, y.name AS year_name
               FROM student_class_history h
               JOIN classes c ON c.id = h.class_id
               LEFT JOIN streams st ON st.id = h.stream_id
               JOIN academic_years y ON y.id = h.academic_year_id
              WHERE h.student_id = ?
              ORDER BY h.effective_date DESC, h.id DESC',
            [$studentId]
        );
    }

    public static function recordPlacement(
        int $studentId,
        int $classId,
        ?int $streamId,
        int $yearId,
        ?int $termId,
        string $action,
        string $effectiveDate,
        ?string $notes,
        ?int $createdBy,
        bool $makeCurrent = true
    ): void {
        $db = static::db();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            if ($makeCurrent) {
                $db->prepare(
                    'UPDATE student_class_history SET is_current = 0 WHERE student_id = ? AND is_current = 1'
                )->execute([$studentId]);
            }

            $db->prepare(
                'INSERT INTO student_class_history
                    (student_id, class_id, stream_id, academic_year_id, term_id, action, is_current, effective_date, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $studentId, $classId, $streamId, $yearId, $termId, $action,
                $makeCurrent ? 1 : 0, $effectiveDate, $notes, $createdBy,
            ]);

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

    public static function assignClass(int $studentId, int $classId, ?int $streamId): void
    {
        static::execute(
            'UPDATE students SET current_class_id = ?, current_stream_id = ? WHERE id = ?',
            [$classId, $streamId, $studentId]
        );
    }

    public static function byClass(int $classId, ?int $streamId = null): array
    {
        $sql    = "SELECT id, first_name, last_name, admission_number, current_stream_id
                     FROM students WHERE status = 'active' AND current_class_id = ?";
        $params = [$classId];
        if ($streamId !== null) {
            $sql .= ' AND current_stream_id = ?';
            $params[] = $streamId;
        }
        $sql .= ' ORDER BY last_name, first_name';
        return static::query($sql, $params);
    }
}
