<?php
declare(strict_types=1);

/**
 * Portal profiles, notices, consents and per-child fee, attendance and result summaries.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class PortalModule extends Model
{
    public static function profile(int $userId, int $schoolId): ?array
    {
        return static::one(
            'SELECT id,user_type,student_id,guardian_id FROM users WHERE id=? AND school_id=? AND is_active=1',
            [$userId,$schoolId]
        );
    }

    public static function consents(int $schoolId, array $profile): array
    {
        if ($profile['user_type'] === 'student' && $profile['student_id']) {
            return static::query(
                "SELECT id,consent_type,granted,granted_at,withdrawn_at FROM consent_records
                  WHERE school_id=? AND subject_type='student' AND student_id=? ORDER BY granted_at DESC LIMIT 100",
                [$schoolId,$profile['student_id']]
            );
        }
        if ($profile['user_type'] === 'parent' && $profile['guardian_id']) {
            return static::query(
                "SELECT id,consent_type,granted,granted_at,withdrawn_at FROM consent_records
                  WHERE school_id=? AND subject_type='guardian' AND guardian_id=? ORDER BY granted_at DESC LIMIT 100",
                [$schoolId,$profile['guardian_id']]
            );
        }
        return [];
    }

    public static function createConsent(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO consent_records (school_id,subject_type,student_id,guardian_id,consent_type,granted,recorded_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$data['school_id'],$data['subject_type'],$data['student_id'],$data['guardian_id'],$data['type'],$data['granted'],$data['user_id']]);
        return (int)static::db()->lastInsertId();
    }

    public static function setConsent(int $id, int $schoolId, array $profile, bool $granted): bool
    {
        $subject       = $profile['user_type'] === 'student' ? 'student' : 'guardian';
        $subjectColumn = $subject === 'student' ? 'student_id' : 'guardian_id';
        $subjectId     = (int)($profile[$subjectColumn] ?? 0);
        $withdraw      = $granted ? 'NULL' : 'NOW()';
        return static::execute(
            "UPDATE consent_records SET granted=?,granted_at=IF(?=1,NOW(),granted_at),withdrawn_at={$withdraw}
              WHERE id=? AND school_id=? AND subject_type=? AND {$subjectColumn}=?",
            [(int)$granted,(int)$granted,$id,$schoolId,$subject,$subjectId]
        ) > 0;
    }

    // ------------------------------------------------- Portal-specific reads

    public static function recentNotices(int $schoolId): array
    {
        return static::query(
            "SELECT id, title, body, published_at FROM announcements
              WHERE school_id=? AND status='published'
                AND (audience='all' OR audience='students' OR audience='parents')
              ORDER BY published_at DESC LIMIT 10",
            [$schoolId]
        );
    }

    public static function studentSummary(int $studentId, int $schoolId): ?array
    {
        return static::one(
            "SELECT s.id, s.admission_number, s.first_name, s.last_name,
                    s.gender, s.date_of_birth, s.photo_path, s.status,
                    c.name AS class_name, st.name AS stream_name,
                    h.name AS house_name
               FROM students s
               LEFT JOIN classes c  ON c.id  = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
               LEFT JOIN houses  h  ON h.id  = s.house_id
              WHERE s.id=? AND s.school_id=?",
            [$studentId, $schoolId]
        );
    }

    public static function guardianSummary(int $guardianId, int $schoolId): array
    {
        return static::query(
            "SELECT s.id, s.admission_number, s.first_name, s.last_name,
                    s.status, c.name AS class_name
               FROM students s
               JOIN student_guardians sg ON sg.student_id = s.id
               LEFT JOIN classes c ON c.id = s.current_class_id
              WHERE sg.guardian_id=? AND s.school_id=?
              ORDER BY s.last_name, s.first_name LIMIT 20",
            [$guardianId, $schoolId]
        );
    }

    /**
     * Students visible to a portal account, each with their own fee balance,
     * attendance record and latest published exam result.
     */
    public static function portalChildren(array $profile, int $schoolId): array
    {
        $students = [];
        if ($profile['user_type'] === 'student' && !empty($profile['student_id'])) {
            $students = static::query(
                "SELECT s.id, s.admission_number, s.first_name, s.last_name,
                        s.status, c.name AS class_name
                   FROM students s
                   LEFT JOIN classes c ON c.id = s.current_class_id
                  WHERE s.id = ? AND s.school_id = ?",
                [(int)$profile['student_id'], $schoolId]
            );
        } elseif ($profile['user_type'] === 'parent' && !empty($profile['guardian_id'])) {
            $students = static::query(
                "SELECT s.id, s.admission_number, s.first_name, s.last_name,
                        s.status, c.name AS class_name
                   FROM students s
                   JOIN student_guardians sg ON sg.student_id = s.id
                   LEFT JOIN classes c ON c.id = s.current_class_id
                  WHERE sg.guardian_id = ? AND s.school_id = ?
                  ORDER BY s.last_name, s.first_name",
                [(int)$profile['guardian_id'], $schoolId]
            );
        }

        foreach ($students as &$student) {
            $studentId = (int)$student['id'];
            $student['fees'] = static::feeSnapshot($studentId, $schoolId);
            $student['attendance'] = static::attendanceSnapshot($studentId, $schoolId);
            $student['result'] = static::latestResult($studentId, $schoolId);
        }
        unset($student);

        return $students;
    }

    public static function feeSnapshot(int $studentId, int $schoolId): array
    {
        $row = static::one(
            "SELECT COALESCE(SUM(total_amount), 0) AS charged,
                    COALESCE(SUM(paid_amount), 0) AS paid,
                    COALESCE(SUM(balance), 0) AS balance,
                    COALESCE(SUM(CASE WHEN balance > 0 AND due_date < CURRENT_DATE
                                      THEN balance ELSE 0 END), 0) AS overdue
               FROM fee_invoices
              WHERE school_id = ? AND student_id = ? AND status <> 'void'",
            [$schoolId, $studentId]
        );

        return $row ?: ['charged' => 0, 'paid' => 0, 'balance' => 0, 'overdue' => 0];
    }

    public static function attendanceSnapshot(int $studentId, int $schoolId): array
    {
        $row = static::one(
            "SELECT COUNT(*) AS days_marked,
                    COALESCE(SUM(status = 'present'), 0) AS days_present,
                    COALESCE(SUM(status = 'absent'), 0) AS days_absent,
                    ROUND(100 * COALESCE(SUM(status = 'present'), 0) / NULLIF(COUNT(*), 0), 1) AS percent
               FROM student_attendance
              WHERE school_id = ? AND student_id = ? AND record_scope = 'daily'",
            [$schoolId, $studentId]
        );

        return $row ?: ['days_marked' => 0, 'days_present' => 0, 'days_absent' => 0, 'percent' => null];
    }

    public static function latestResult(int $studentId, int $schoolId): ?array
    {
        return static::one(
            "SELECT e.name AS exam_name, e.published_at,
                    rs.average_percent, rs.total_points,
                    (SELECT COUNT(*) + 1 FROM result_summaries x
                      WHERE x.exam_id = rs.exam_id AND x.class_id = rs.class_id
                        AND x.average_percent > rs.average_percent) AS class_rank,
                    (SELECT COUNT(*) FROM result_summaries x
                      WHERE x.exam_id = rs.exam_id AND x.class_id = rs.class_id) AS class_size
               FROM result_summaries rs
               JOIN exams e ON e.id = rs.exam_id AND e.school_id = ?
              WHERE rs.student_id = ? AND e.status = 'published'
              ORDER BY e.published_at DESC, e.id DESC
              LIMIT 1",
            [$schoolId, $studentId]
        );
    }
}
