<?php
declare(strict_types=1);

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
}
