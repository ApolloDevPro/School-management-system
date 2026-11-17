<?php
declare(strict_types=1);

final class BoardingModule extends Model
{
    public static function students(int $schoolId): array
    {
        return static::query(
            "SELECT id,admission_number,first_name,last_name FROM students WHERE school_id=? AND status='active' ORDER BY last_name,first_name LIMIT 500",
            [$schoolId]
        );
    }

    public static function years(int $schoolId): array
    {
        return static::query('SELECT id,name,is_active FROM academic_years WHERE school_id=? ORDER BY is_active DESC,start_date DESC',[$schoolId]);
    }

    public static function terms(int $schoolId): array
    {
        return static::query(
            'SELECT t.id,t.name,t.academic_year_id FROM terms t JOIN academic_years y ON y.id=t.academic_year_id
              WHERE y.school_id=? ORDER BY y.is_active DESC,t.term_number DESC',
            [$schoolId]
        );
    }

    public static function registrations(int $schoolId): array
    {
        return static::query(
            'SELECT r.id,r.status,r.registered_at,s.id AS student_id,s.admission_number,s.first_name,s.last_name,
                    y.name AS year_name,t.name AS term_name,d.name AS dormitory_name
               FROM boarding_registrations r JOIN students s ON s.id=r.student_id
               JOIN academic_years y ON y.id=r.academic_year_id JOIN terms t ON t.id=r.term_id
               LEFT JOIN dormitories d ON d.id=r.dormitory_id
              WHERE s.school_id=? ORDER BY r.registered_at DESC LIMIT 300',
            [$schoolId]
        );
    }

    public static function createRegistration(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO boarding_registrations (student_id,academic_year_id,term_id,dormitory_id,emergency_contact,registered_by,notes)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$data['student_id'],$data['year_id'],$data['term_id'],$data['dormitory_id'],$data['emergency_contact'],$data['user_id'],$data['notes']]);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        $timeField = $status === 'checked_in' ? 'checked_in_at' : 'checked_out_at';
        return static::execute(
            "UPDATE boarding_registrations r JOIN students s ON s.id=r.student_id
                SET r.status=?,r.{$timeField}=NOW() WHERE r.id=? AND s.school_id=?",
            [$status,$id,$schoolId]
        ) > 0;
    }

    public static function validStudent(int $studentId, int $schoolId): bool
    {
        return (bool)static::scalar('SELECT COUNT(*) FROM students WHERE id=? AND school_id=? AND status=?',[$studentId,$schoolId,'active']);
    }

    public static function validYearTerm(int $yearId, int $termId, int $schoolId): bool
    {
        return (bool)static::scalar(
            'SELECT COUNT(*) FROM terms t JOIN academic_years y ON y.id=t.academic_year_id
              WHERE y.id=? AND t.id=? AND y.school_id=?',
            [$yearId,$termId,$schoolId]
        );
    }

    public static function dormitories(int $schoolId): array
    {
        return static::query('SELECT id,name FROM dormitories WHERE school_id=? AND status=? ORDER BY name',[$schoolId,'active']);
    }
}
