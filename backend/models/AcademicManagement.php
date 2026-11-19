<?php
declare(strict_types=1);

/**
 * Curricula, subjects, combinations, allocations, timetables, homework, syllabus and coverage queries.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
final class AcademicManagement extends Model
{
    protected static string $table = 'subjects';

    public static function curricula(int $schoolId): array
    {
        return static::query(
            'SELECT * FROM curricula WHERE school_id = ? ORDER BY name, code',
            [$schoolId]
        );
    }

    public static function curriculum(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM curricula WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function saveCurriculum(int $schoolId, array $data, ?int $id = null): int
    {
        if ($id !== null) {
            static::execute(
                'UPDATE curricula SET name = ?, code = ?, level_scope = ?, description = ?, status = ?
                  WHERE id = ? AND school_id = ?',
                [$data['name'], $data['code'], $data['level_scope'], $data['description'], $data['status'], $id, $schoolId]
            );
            return $id;
        }

        static::execute(
            'INSERT INTO curricula (school_id, name, code, level_scope, description, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$schoolId, $data['name'], $data['code'], $data['level_scope'], $data['description'], $data['status']]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function curriculumCodeTaken(int $schoolId, string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM curricula WHERE school_id = ? AND code = ?';
        $params = [$schoolId, $code];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function subjects(int $schoolId): array
    {
        return static::query(
            'SELECT s.*, d.name AS department_name
               FROM subjects s
               LEFT JOIN departments d ON d.id = s.department_id AND d.school_id = s.school_id
              WHERE s.school_id = ?
              ORDER BY s.order_index, s.name',
            [$schoolId]
        );
    }

    public static function subject(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM subjects WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function saveSubject(int $schoolId, array $data, ?int $id = null): int
    {
        if ($id !== null) {
            static::execute(
                'UPDATE subjects
                    SET code = ?, name = ?, short_name = ?, level = ?, category = ?, department_id = ?,
                        uneb_subject_code = ?, credit_points = ?, is_composite = ?, order_index = ?, is_active = ?
                  WHERE id = ? AND school_id = ?',
                [
                    $data['code'], $data['name'], $data['short_name'], $data['level'], $data['category'],
                    $data['department_id'], $data['uneb_subject_code'], $data['credit_points'],
                    $data['is_composite'], $data['order_index'], $data['is_active'], $id, $schoolId,
                ]
            );
            return $id;
        }

        static::execute(
            'INSERT INTO subjects
                (school_id, code, name, short_name, level, category, department_id, uneb_subject_code,
                 credit_points, is_composite, order_index, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $schoolId, $data['code'], $data['name'], $data['short_name'], $data['level'], $data['category'],
                $data['department_id'], $data['uneb_subject_code'], $data['credit_points'],
                $data['is_composite'], $data['order_index'], $data['is_active'],
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function subjectCodeTaken(int $schoolId, string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM subjects WHERE school_id = ? AND code = ?';
        $params = [$schoolId, $code];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function departments(int $schoolId): array
    {
        return static::query(
            'SELECT id, name FROM departments WHERE school_id = ? AND is_active = 1 ORDER BY name',
            [$schoolId]
        );
    }

    public static function classes(int $schoolId): array
    {
        return static::query(
            'SELECT id, name FROM classes WHERE school_id = ? AND is_active = 1 ORDER BY order_index, name',
            [$schoolId]
        );
    }

    public static function streams(int $schoolId): array
    {
        return static::query(
            'SELECT st.id, st.class_id, st.name AS stream_name, c.name AS class_name
               FROM streams st
               JOIN classes c ON c.id = st.class_id
              WHERE c.school_id = ? AND st.is_active = 1
              ORDER BY c.order_index, c.name, st.name',
            [$schoolId]
        );
    }

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, is_active FROM academic_years WHERE school_id = ? ORDER BY start_date DESC, id DESC',
            [$schoolId]
        );
    }

    public static function year(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM academic_years WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function terms(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT t.id, t.name, t.term_number, t.is_active
               FROM terms t
               JOIN academic_years y ON y.id = t.academic_year_id
              WHERE y.school_id = ? AND y.id = ?
              ORDER BY t.term_number',
            [$schoolId, $yearId]
        );
    }

    public static function term(int $id, int $schoolId, int $yearId): ?array
    {
        return static::one(
            'SELECT t.* FROM terms t
              JOIN academic_years y ON y.id = t.academic_year_id
             WHERE t.id = ? AND y.school_id = ? AND y.id = ?',
            [$id, $schoolId, $yearId]
        );
    }

    public static function staff(int $schoolId): array
    {
        return static::query(
            "SELECT id, staff_number, first_name, last_name
               FROM staff
              WHERE school_id = ? AND employment_status IN ('active','probation','contract')
              ORDER BY first_name, last_name",
            [$schoolId]
        );
    }

    public static function subjectAllocations(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT a.*, s.code AS subject_code, s.name AS subject_name, c.name AS class_name,
                    st.name AS stream_name, t.name AS term_name
               FROM subject_allocations a
               JOIN subjects s ON s.id = a.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = a.class_id AND c.school_id = ?
               LEFT JOIN streams st ON st.id = a.stream_id
               LEFT JOIN terms t ON t.id = a.term_id
              WHERE a.academic_year_id = ?
              ORDER BY c.order_index, c.name, st.name, s.order_index, s.name',
            [$schoolId, $schoolId, $yearId]
        );
    }

    public static function teacherAllocations(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT a.*, s.code AS subject_code, s.name AS subject_name, c.name AS class_name,
                    st.name AS stream_name, t.name AS term_name,
                    CONCAT(f.first_name, \' \', f.last_name) AS teacher_name
               FROM teacher_allocations a
               JOIN subjects s ON s.id = a.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = a.class_id AND c.school_id = ?
               JOIN staff f ON f.id = a.staff_id AND f.school_id = ?
               LEFT JOIN streams st ON st.id = a.stream_id
               LEFT JOIN terms t ON t.id = a.term_id
              WHERE a.academic_year_id = ?
              ORDER BY c.order_index, c.name, s.order_index, s.name, teacher_name',
            [$schoolId, $schoolId, $schoolId, $yearId]
        );
    }

    public static function saveSubjectAllocation(
        int $subjectId,
        int $classId,
        ?int $streamId,
        int $yearId,
        ?int $termId,
        bool $compulsory,
        ?int $userId
    ): int {
        $existing = static::one(
            'SELECT id FROM subject_allocations
              WHERE subject_id = ? AND class_id = ? AND stream_id <=> ?
                AND academic_year_id = ? AND term_id <=> ?',
            [$subjectId, $classId, $streamId, $yearId, $termId]
        );
        if ($existing) {
            static::execute(
                'UPDATE subject_allocations SET is_compulsory = ?, is_active = 1 WHERE id = ?',
                [$compulsory ? 1 : 0, $existing['id']]
            );
            return (int)$existing['id'];
        }
        static::execute(
            'INSERT INTO subject_allocations
                (subject_id, class_id, stream_id, academic_year_id, term_id, is_compulsory, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
            [$subjectId, $classId, $streamId, $yearId, $termId, $compulsory ? 1 : 0, $userId]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function subjectAllocation(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT a.* FROM subject_allocations a
               JOIN subjects s ON s.id = a.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = a.class_id AND c.school_id = ?
              WHERE a.id = ?',
            [$schoolId, $schoolId, $id]
        );
    }

    public static function saveTeacherAllocation(
        int $staffId,
        int $subjectId,
        int $classId,
        ?int $streamId,
        int $yearId,
        ?int $termId,
        int $lessonsPerWeek,
        bool $primary,
        ?int $userId
    ): int {
        $existing = static::one(
            'SELECT id FROM teacher_allocations
              WHERE staff_id = ? AND subject_id = ? AND class_id = ? AND stream_id <=> ?
                AND academic_year_id = ? AND term_id <=> ?',
            [$staffId, $subjectId, $classId, $streamId, $yearId, $termId]
        );
        if ($existing) {
            static::execute(
                'UPDATE teacher_allocations SET lessons_per_week = ?, is_primary = ? WHERE id = ?',
                [$lessonsPerWeek, $primary ? 1 : 0, $existing['id']]
            );
            return (int)$existing['id'];
        }
        static::execute(
            'INSERT INTO teacher_allocations
                (staff_id, subject_id, class_id, stream_id, academic_year_id, term_id,
                 lessons_per_week, is_primary, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$staffId, $subjectId, $classId, $streamId, $yearId, $termId, $lessonsPerWeek, $primary ? 1 : 0, $userId]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function teacherAllocation(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT a.* FROM teacher_allocations a
               JOIN staff f ON f.id = a.staff_id AND f.school_id = ?
               JOIN subjects s ON s.id = a.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = a.class_id AND c.school_id = ?
              WHERE a.id = ?',
            [$schoolId, $schoolId, $schoolId, $id]
        );
    }

    public static function removeTeacherAllocation(int $id, int $schoolId): void
    {
        static::execute(
            'DELETE a FROM teacher_allocations a
               JOIN staff f ON f.id = a.staff_id AND f.school_id = ?
               JOIN subjects s ON s.id = a.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = a.class_id AND c.school_id = ?
              WHERE a.id = ?',
            [$schoolId, $schoolId, $schoolId, $id]
        );
    }

    public static function schemes(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT w.*, s.code AS subject_code, s.name AS subject_name, c.name AS class_name,
                    st.name AS stream_name, t.name AS term_name,
                    CONCAT(f.first_name, \' \', f.last_name) AS teacher_name
               FROM schemes_of_work w
               JOIN staff f ON f.id = w.staff_id AND f.school_id = ?
               JOIN subjects s ON s.id = w.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = w.class_id AND c.school_id = ?
               LEFT JOIN streams st ON st.id = w.stream_id
               JOIN terms t ON t.id = w.term_id
              WHERE w.academic_year_id = ?
              ORDER BY t.term_number, w.week_number, c.name, s.name',
            [$schoolId, $schoolId, $schoolId, $yearId]
        );
    }

    public static function scheme(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT w.* FROM schemes_of_work w
               JOIN staff f ON f.id = w.staff_id AND f.school_id = ?
               JOIN subjects s ON s.id = w.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = w.class_id AND c.school_id = ?
               JOIN academic_years y ON y.id = w.academic_year_id AND y.school_id = ?
              WHERE w.id = ?',
            [$schoolId, $schoolId, $schoolId, $schoolId, $id]
        );
    }

    public static function schemeKeyExists(
        int $staffId,
        int $subjectId,
        int $classId,
        int $termId,
        int $weekNumber,
        ?int $ignoreId = null
    ): bool {
        $sql = 'SELECT COUNT(*) FROM schemes_of_work
                 WHERE staff_id = ? AND subject_id = ? AND class_id = ? AND term_id = ? AND week_number = ?';
        $params = [$staffId, $subjectId, $classId, $termId, $weekNumber];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function saveScheme(array $data, ?int $id, ?int $reviewerId): int
    {
        if ($id !== null) {
            static::execute(
                'UPDATE schemes_of_work
                    SET staff_id = ?, subject_id = ?, class_id = ?, stream_id = ?, academic_year_id = ?,
                        term_id = ?, week_number = ?, week_starting = ?, topic = ?, sub_topics = ?,
                        objectives = ?, learning_experiences = ?, teaching_methods = ?,
                        learning_materials = ?, assessment = ?, remarks = ?, status = ?,
                        submitted_at = ?, reviewed_by = NULL, reviewed_at = NULL, review_comments = NULL
                  WHERE id = ?',
                [
                    $data['staff_id'], $data['subject_id'], $data['class_id'], $data['stream_id'],
                    $data['academic_year_id'], $data['term_id'], $data['week_number'], $data['week_starting'],
                    $data['topic'], $data['sub_topics'], $data['objectives'], $data['learning_experiences'],
                    $data['teaching_methods'], $data['learning_materials'], $data['assessment'], $data['remarks'],
                    $data['status'], $data['submitted_at'], $id,
                ]
            );
            return $id;
        }
        static::execute(
            'INSERT INTO schemes_of_work
                (staff_id, subject_id, class_id, stream_id, academic_year_id, term_id, week_number,
                 week_starting, topic, sub_topics, objectives, learning_experiences, teaching_methods,
                 learning_materials, assessment, remarks, status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['staff_id'], $data['subject_id'], $data['class_id'], $data['stream_id'],
                $data['academic_year_id'], $data['term_id'], $data['week_number'], $data['week_starting'],
                $data['topic'], $data['sub_topics'], $data['objectives'], $data['learning_experiences'],
                $data['teaching_methods'], $data['learning_materials'], $data['assessment'], $data['remarks'],
                $data['status'], $data['submitted_at'],
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function rooms(int $schoolId): array
    {
        return static::query(
            'SELECT id, name FROM rooms WHERE school_id = ? AND is_active = 1 ORDER BY name',
            [$schoolId]
        );
    }

    public static function timetables(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT t.*, c.name AS class_name, st.name AS stream_name, tm.name AS term_name
               FROM timetables t
               JOIN classes c ON c.id = t.class_id AND c.school_id = ?
               LEFT JOIN streams st ON st.id = t.stream_id
               JOIN terms tm ON tm.id = t.term_id
              WHERE t.school_id = ? AND t.academic_year_id = ?
              ORDER BY c.order_index, c.name, st.name, tm.term_number, t.id DESC',
            [$schoolId, $schoolId, $yearId]
        );
    }

    public static function timetable(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT t.*, c.name AS class_name, st.name AS stream_name, tm.name AS term_name
               FROM timetables t
               JOIN classes c ON c.id = t.class_id AND c.school_id = ?
               LEFT JOIN streams st ON st.id = t.stream_id
               JOIN terms tm ON tm.id = t.term_id
              WHERE t.id = ? AND t.school_id = ?',
            [$schoolId, $id, $schoolId]
        );
    }

    public static function timetableKeyExists(
        int $schoolId,
        int $classId,
        ?int $streamId,
        int $yearId,
        int $termId,
        ?int $ignoreId = null
    ): bool {
        $sql = 'SELECT COUNT(*) FROM timetables
                 WHERE school_id = ? AND class_id = ? AND stream_id <=> ?
                   AND academic_year_id = ? AND term_id = ?';
        $params = [$schoolId, $classId, $streamId, $yearId, $termId];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int)static::scalar($sql, $params) > 0;
    }

    public static function saveTimetable(int $schoolId, array $data, ?int $userId = null): int
    {
        static::execute(
            'INSERT INTO timetables
                (school_id, name, scope, class_id, stream_id, academic_year_id, term_id, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $schoolId, $data['name'], $data['scope'], $data['class_id'], $data['stream_id'],
                $data['academic_year_id'], $data['term_id'], $data['status'], $userId,
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function removeTimetable(int $id, int $schoolId): void
    {
        static::execute('DELETE FROM timetables WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function timetableSlots(int $timetableId): array
    {
        return static::query(
            'SELECT sl.*, s.code AS subject_code, s.name AS subject_name, s.short_name AS subject_short_name,
                    CONCAT(f.first_name, \' \', f.last_name) AS teacher_name, r.name AS room_name,
                    c.name AS class_name, st.name AS stream_name
               FROM timetable_slots sl
               JOIN subjects s ON s.id = sl.subject_id
               JOIN staff f ON f.id = sl.staff_id
               JOIN classes c ON c.id = sl.class_id
               LEFT JOIN streams st ON st.id = sl.stream_id
               LEFT JOIN rooms r ON r.id = sl.room_id
              WHERE sl.timetable_id = ?
              ORDER BY FIELD(sl.day_of_week, \'Monday\', \'Tuesday\', \'Wednesday\', \'Thursday\',
                      \'Friday\', \'Saturday\', \'Sunday\'), sl.period_number',
            [$timetableId]
        );
    }

    public static function saveTimetableSlot(int $timetableId, array $data): int
    {
        static::execute(
            'INSERT INTO timetable_slots
                (timetable_id, day_of_week, period_number, start_time, end_time, subject_id, staff_id,
                 class_id, stream_id, room_id, lesson_type, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $timetableId, $data['day_of_week'], $data['period_number'], $data['start_time'],
                $data['end_time'], $data['subject_id'], $data['staff_id'], $data['class_id'],
                $data['stream_id'], $data['room_id'], $data['lesson_type'], $data['notes'],
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function timetableSlot(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT sl.*, t.school_id, t.academic_year_id, t.term_id, t.status AS timetable_status
               FROM timetable_slots sl
               JOIN timetables t ON t.id = sl.timetable_id AND t.school_id = ?
              WHERE sl.id = ?',
            [$schoolId, $id]
        );
    }

    public static function removeTimetableSlot(int $id, int $schoolId): void
    {
        static::execute(
            'DELETE sl FROM timetable_slots sl
               JOIN timetables t ON t.id = sl.timetable_id AND t.school_id = ?
              WHERE sl.id = ?',
            [$schoolId, $id]
        );
    }

    public static function teacherSlotClash(
        int $yearId,
        int $termId,
        string $day,
        int $period,
        int $staffId,
        ?int $ignoreSlotId = null
    ): ?array {
        $sql = 'SELECT sl.id, c.name AS class_name, st.name AS stream_name
                  FROM timetable_slots sl
                  JOIN timetables t ON t.id = sl.timetable_id
                  JOIN classes c ON c.id = sl.class_id
                  LEFT JOIN streams st ON st.id = sl.stream_id
                 WHERE t.academic_year_id = ? AND t.term_id = ?
                   AND sl.day_of_week = ? AND sl.period_number = ? AND sl.staff_id = ?';
        $params = [$yearId, $termId, $day, $period, $staffId];
        if ($ignoreSlotId !== null) {
            $sql .= ' AND sl.id <> ?';
            $params[] = $ignoreSlotId;
        }
        return static::one($sql . ' LIMIT 1', $params);
    }

    public static function classSlotClash(
        int $yearId,
        int $termId,
        string $day,
        int $period,
        int $classId,
        ?int $ignoreSlotId = null
    ): ?array {
        $sql = 'SELECT sl.id, c.name AS class_name, st.name AS stream_name
                  FROM timetable_slots sl
                  JOIN timetables t ON t.id = sl.timetable_id
                  JOIN classes c ON c.id = sl.class_id
                  LEFT JOIN streams st ON st.id = sl.stream_id
                 WHERE t.academic_year_id = ? AND t.term_id = ?
                   AND sl.day_of_week = ? AND sl.period_number = ? AND sl.class_id = ?';
        $params = [$yearId, $termId, $day, $period, $classId];
        if ($ignoreSlotId !== null) {
            $sql .= ' AND sl.id <> ?';
            $params[] = $ignoreSlotId;
        }
        return static::one($sql . ' LIMIT 1', $params);
    }

    public static function homeworkList(int $schoolId, int $yearId, ?int $classId): array
    {
        $sql = 'SELECT h.*, s.code AS subject_code, s.name AS subject_name, c.name AS class_name,
                       st.name AS stream_name, tm.name AS term_name,
                       CONCAT(f.first_name, \' \', f.last_name) AS teacher_name,
                       (SELECT COUNT(*) FROM homework_submissions hs
                         WHERE hs.homework_id = h.id AND hs.status <> \'pending\') AS submitted_count,
                       (SELECT COUNT(*) FROM students sd
                         WHERE sd.school_id = ? AND sd.status = \'active\'
                           AND sd.current_class_id = h.class_id
                           AND (h.stream_id IS NULL OR sd.current_stream_id <=> h.stream_id)) AS student_count
                  FROM homework h
                  JOIN subjects s ON s.id = h.subject_id AND s.school_id = ?
                  JOIN classes c ON c.id = h.class_id AND c.school_id = ?
                  LEFT JOIN streams st ON st.id = h.stream_id
                  JOIN terms tm ON tm.id = h.term_id
                  JOIN staff f ON f.id = h.staff_id AND f.school_id = ?
                 WHERE h.academic_year_id = ?';
        $params = [$schoolId, $schoolId, $schoolId, $schoolId, $yearId];
        if ($classId !== null) {
            $sql .= ' AND h.class_id = ?';
            $params[] = $classId;
        }
        $sql .= ' ORDER BY h.due_date DESC, h.id DESC';
        return static::query($sql, $params);
    }

    public static function homework(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT h.*, s.code AS subject_code, s.name AS subject_name, c.name AS class_name,
                    st.name AS stream_name, CONCAT(f.first_name, \' \', f.last_name) AS teacher_name
               FROM homework h
               JOIN subjects s ON s.id = h.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = h.class_id AND c.school_id = ?
               LEFT JOIN streams st ON st.id = h.stream_id
               JOIN staff f ON f.id = h.staff_id AND f.school_id = ?
              WHERE h.id = ?',
            [$schoolId, $schoolId, $schoolId, $id]
        );
    }

    public static function saveHomework(array $data): int
    {
        static::execute(
            'INSERT INTO homework
                (staff_id, subject_id, class_id, stream_id, academic_year_id, term_id, title,
                 description, assigned_date, due_date, total_marks, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['staff_id'], $data['subject_id'], $data['class_id'], $data['stream_id'],
                $data['academic_year_id'], $data['term_id'], $data['title'], $data['description'],
                $data['assigned_date'], $data['due_date'], $data['total_marks'], $data['status'],
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function homeworkRoster(array $homework, int $schoolId): array
    {
        return static::query(
            'SELECT s.id, s.admission_number, s.first_name, s.last_name,
                    sub.status AS submission_status, sub.submitted_at
               FROM students s
               LEFT JOIN homework_submissions sub ON sub.homework_id = ? AND sub.student_id = s.id
              WHERE s.school_id = ? AND s.current_class_id = ? AND s.status = \'active\'
                AND (? IS NULL OR s.current_stream_id <=> ?)
              ORDER BY s.last_name, s.first_name',
            [
                (int)$homework['id'], $schoolId, (int)$homework['class_id'],
                $homework['stream_id'] !== null ? (int)$homework['stream_id'] : null,
                $homework['stream_id'] !== null ? (int)$homework['stream_id'] : null,
            ]
        );
    }

    public static function saveHomeworkSubmission(int $homeworkId, int $studentId, bool $submitted, string $dueDate): void
    {
        $existing = static::one(
            'SELECT id, status FROM homework_submissions WHERE homework_id = ? AND student_id = ?',
            [$homeworkId, $studentId]
        );
        if ($existing) {
            if (in_array($existing['status'], ['marked', 'returned'], true)) {
                return;
            }
            if ($submitted) {
                $late = date('Y-m-d') > $dueDate;
                static::execute(
                    'UPDATE homework_submissions SET status = ?, submitted_at = ? WHERE id = ?',
                    [$late ? 'late' : 'submitted', date('Y-m-d H:i:s'), $existing['id']]
                );
            } else {
                static::execute(
                    'UPDATE homework_submissions SET status = \'pending\', submitted_at = NULL WHERE id = ?',
                    [$existing['id']]
                );
            }
            return;
        }
        if ($submitted) {
            $late = date('Y-m-d') > $dueDate;
            static::execute(
                'INSERT INTO homework_submissions (homework_id, student_id, submitted_at, status)
                 VALUES (?, ?, ?, ?)',
                [$homeworkId, $studentId, date('Y-m-d H:i:s'), $late ? 'late' : 'submitted']
            );
        }
    }

    public static function upcomingEvents(int $schoolId, string $fromDate): array
    {
        return static::query(
            'SELECT e.*, y.name AS year_name, tm.name AS term_name
               FROM academic_events e
               LEFT JOIN academic_years y ON y.id = e.academic_year_id
               LEFT JOIN terms tm ON tm.id = e.term_id
              WHERE e.school_id = ? AND COALESCE(e.end_date, e.start_date) >= ?
              ORDER BY e.start_date ASC, e.start_time ASC, e.id ASC
              LIMIT 100',
            [$schoolId, $fromDate]
        );
    }

    public static function saveEvent(int $schoolId, array $data, ?int $userId = null): int
    {
        static::execute(
            'INSERT INTO academic_events
                (school_id, academic_year_id, term_id, title, event_type, start_date, end_date,
                 description, audience, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $schoolId, $data['academic_year_id'], $data['term_id'], $data['title'],
                $data['event_type'], $data['start_date'], $data['end_date'], $data['description'],
                $data['audience'], $userId,
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function syllabi(int $schoolId): array
    {
        return static::query(
            'SELECT sy.*, s.code AS subject_code, s.name AS subject_name, cu.name AS curriculum_name
               FROM syllabuses sy
               JOIN subjects s ON s.id = sy.subject_id AND s.school_id = ?
               LEFT JOIN curricula cu ON cu.id = sy.curriculum_id AND cu.school_id = s.school_id
              ORDER BY s.name, sy.is_current DESC, sy.id DESC',
            [$schoolId]
        );
    }

    public static function syllabus(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT sy.*, s.code AS subject_code, s.name AS subject_name, s.level AS subject_level
               FROM syllabuses sy
               JOIN subjects s ON s.id = sy.subject_id AND s.school_id = ?
              WHERE sy.id = ?',
            [$schoolId, $id]
        );
    }

    public static function saveSyllabus(int $schoolId, array $data, ?int $userId = null): int
    {
        static::execute(
            'INSERT INTO syllabuses (subject_id, curriculum_id, title, level, version, is_current)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['subject_id'], $data['curriculum_id'], $data['title'], $data['level'],
                $data['version'], $data['is_current'],
            ]
        );
        $id = (int)static::db()->lastInsertId();
        if ($data['is_current']) {
            static::execute(
                'UPDATE syllabuses SET is_current = 0 WHERE subject_id = ? AND level = ? AND id <> ?',
                [$data['subject_id'], $data['level'], $id]
            );
        }
        return $id;
    }

    public static function syllabusTopics(int $syllabusId): array
    {
        return static::query(
            'SELECT * FROM syllabus_topics WHERE syllabus_id = ? ORDER BY order_index, id',
            [$syllabusId]
        );
    }

    public static function saveSyllabusTopic(int $syllabusId, array $data, ?int $userId = null): int
    {
        static::execute(
            'INSERT INTO syllabus_topics
                (syllabus_id, order_index, code, title, sub_topics, learning_outcomes,
                 suggested_lessons, is_project_topic)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $syllabusId, $data['order_index'], $data['code'], $data['title'],
                $data['sub_topics'], $data['learning_outcomes'], $data['suggested_lessons'],
                $data['is_project_topic'],
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function currentSyllabus(int $schoolId, int $subjectId): ?array
    {
        return static::one(
            'SELECT sy.*, s.code AS subject_code, s.name AS subject_name
               FROM syllabuses sy
               JOIN subjects s ON s.id = sy.subject_id AND s.school_id = ?
              WHERE sy.subject_id = ? AND sy.is_current = 1
              ORDER BY sy.id DESC
              LIMIT 1',
            [$schoolId, $subjectId]
        );
    }

    public static function coverageRows(int $subjectId, int $classId, ?int $streamId, int $termId): array
    {
        $sql = 'SELECT sc.*, st.title AS topic_title, st.order_index
                  FROM syllabus_coverage sc
                  JOIN syllabus_topics st ON st.id = sc.topic_id
                  JOIN syllabuses sy ON sy.id = st.syllabus_id
                 WHERE sy.subject_id = ? AND sc.class_id = ? AND sc.term_id = ?';
        $params = [$subjectId, $classId, $termId];
        if ($streamId !== null) {
            $sql .= ' AND (sc.stream_id IS NULL OR sc.stream_id = ?)';
            $params[] = $streamId;
        }
        return static::query($sql . ' ORDER BY st.order_index, st.id', $params);
    }

    public static function saveCoverage(
        int $topicId,
        int $classId,
        ?int $streamId,
        int $yearId,
        int $termId,
        string $taughtDate,
        ?int $taughtBy,
        int $lessonsTaken = 1
    ): int {
        $existing = static::one(
            'SELECT id FROM syllabus_coverage
              WHERE topic_id = ? AND class_id = ? AND stream_id <=> ? AND term_id = ?',
            [$topicId, $classId, $streamId, $termId]
        );
        if ($existing) {
            static::execute(
                'UPDATE syllabus_coverage
                    SET taught_date = ?, taught_by = ?, lessons_taken = ?, coverage_percent = 100.00
                  WHERE id = ?',
                [$taughtDate, $taughtBy, $lessonsTaken, $existing['id']]
            );
            return (int)$existing['id'];
        }
        static::execute(
            'INSERT INTO syllabus_coverage
                (topic_id, class_id, stream_id, academic_year_id, term_id, taught_date, taught_by,
                 lessons_taken, coverage_percent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 100.00)',
            [$topicId, $classId, $streamId, $yearId, $termId, $taughtDate, $taughtBy, $lessonsTaken]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function removeCoverage(int $topicId, int $classId, ?int $streamId, int $termId): void
    {
        static::execute(
            'DELETE FROM syllabus_coverage
              WHERE topic_id = ? AND class_id = ? AND stream_id <=> ? AND term_id = ?',
            [$topicId, $classId, $streamId, $termId]
        );
    }

    public static function workload(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT f.id, f.staff_number, f.first_name, f.last_name,
                    CONCAT(f.first_name, \' \', f.last_name) AS teacher_name,
                    COUNT(*) AS allocation_count,
                    COALESCE(SUM(ta.lessons_per_week), 0) AS lessons_per_week
               FROM teacher_allocations ta
               JOIN staff f ON f.id = ta.staff_id AND f.school_id = ?
              WHERE ta.academic_year_id = ?
              GROUP BY f.id, f.staff_number, f.first_name, f.last_name
              ORDER BY lessons_per_week DESC, teacher_name ASC',
            [$schoolId, $yearId]
        );
    }

    public static function activities(int $schoolId, int $yearId): array
    {
        return static::query(
            'SELECT a.*, s.code AS subject_code, s.name AS subject_name, c.name AS class_name,
                    st.name AS stream_name, tm.name AS term_name
               FROM integrated_activities a
               LEFT JOIN subjects s ON s.id = a.subject_id AND s.school_id = ?
               JOIN classes c ON c.id = a.class_id AND c.school_id = ?
               LEFT JOIN streams st ON st.id = a.stream_id
               JOIN terms tm ON tm.id = a.term_id
              WHERE a.school_id = ? AND a.academic_year_id = ?
              ORDER BY (a.due_date IS NULL), a.due_date, a.id DESC',
            [$schoolId, $schoolId, $schoolId, $yearId]
        );
    }

    public static function activity(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT * FROM integrated_activities WHERE id = ? AND school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function saveActivity(int $schoolId, array $data, ?int $userId = null): int
    {
        static::execute(
            'INSERT INTO integrated_activities
                (school_id, subject_id, class_id, stream_id, academic_year_id, term_id, title,
                 activity_type, description, instructions, max_score, start_date, due_date,
                 status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $schoolId, $data['subject_id'], $data['class_id'], $data['stream_id'],
                $data['academic_year_id'], $data['term_id'], $data['title'], $data['activity_type'],
                $data['description'], $data['instructions'], $data['max_score'], $data['start_date'],
                $data['due_date'], $data['status'], $userId,
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function setActivityStatus(int $id, int $schoolId, string $status): void
    {
        static::execute(
            'UPDATE integrated_activities SET status = ? WHERE id = ? AND school_id = ?',
            [$status, $id, $schoolId]
        );
    }
}
