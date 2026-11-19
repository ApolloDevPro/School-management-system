<?php
declare(strict_types=1);

/**
 * Attendance persistence and summary queries.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class AttendanceModule extends Model
{
    public static function classes(int $schoolId): array
    {
        return static::query(
            'SELECT id, name FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY order_index, name',
            [$schoolId]
        );
    }

    public static function activeYearAndTerm(int $schoolId): ?array
    {
        return static::one(
            'SELECT y.id AS year_id, t.id AS term_id
               FROM academic_years y
               JOIN terms t ON t.academic_year_id = y.id
              WHERE y.school_id = ? AND y.is_active = 1 AND t.is_active = 1
              LIMIT 1',
            [$schoolId]
        );
    }

    public static function studentsForDay(int $schoolId, int $classId, string $date): array
    {
        return static::query(
            "SELECT s.id, s.admission_number, s.first_name, s.last_name, s.current_stream_id,
                    a.status AS attendance_status, a.minutes_late, a.reason
               FROM students s
               LEFT JOIN student_attendance a
                 ON a.student_id = s.id AND a.school_id = s.school_id
                AND a.attendance_date = ? AND a.record_scope = 'daily'
              WHERE s.school_id = ? AND s.current_class_id = ? AND s.status = 'active'
              ORDER BY s.last_name, s.first_name",
            [$date, $schoolId, $classId]
        );
    }

    public static function staffForDay(int $schoolId, string $date): array
    {
        return static::query(
            "SELECT s.id, s.staff_number, s.first_name, s.last_name,
                    a.status AS attendance_status, a.time_in, a.time_out, a.minutes_late
               FROM staff s
               LEFT JOIN staff_attendance a
                 ON a.staff_id = s.id AND a.school_id = s.school_id AND a.attendance_date = ?
              WHERE s.school_id = ?
                AND s.employment_status IN ('active','probation','contract')
              ORDER BY s.last_name, s.first_name",
            [$date, $schoolId]
        );
    }

    public static function classBelongsToSchool(int $classId, int $schoolId): bool
    {
        return static::one('SELECT id FROM classes WHERE id = ? AND school_id = ?', [$classId, $schoolId]) !== null;
    }

    public static function studentInClass(int $studentId, int $classId, int $schoolId): bool
    {
        return static::one(
            "SELECT id FROM students
              WHERE id = ? AND current_class_id = ? AND school_id = ? AND status = 'active'",
            [$studentId, $classId, $schoolId]
        ) !== null;
    }

    public static function staffBelongsToSchool(int $staffId, int $schoolId): bool
    {
        return static::one(
            "SELECT id FROM staff WHERE id = ? AND school_id = ?
              AND employment_status IN ('active','probation','contract')",
            [$staffId, $schoolId]
        ) !== null;
    }
}
