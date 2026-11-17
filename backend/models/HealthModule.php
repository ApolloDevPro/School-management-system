<?php
declare(strict_types=1);

final class HealthModule extends Model
{
    public static function students(int $schoolId): array
    {
        return static::query(
            "SELECT id, admission_number, first_name, last_name
               FROM students
              WHERE school_id = ? AND status IN ('active','suspended')
              ORDER BY last_name, first_name",
            [$schoolId]
        );
    }

    public static function student(int $studentId, int $schoolId): ?array
    {
        return static::one('SELECT id FROM students WHERE id = ? AND school_id = ?', [$studentId, $schoolId]);
    }

    public static function medicalRecords(int $schoolId, bool $includeConfidential): array
    {
        $sql = 'SELECT s.id AS student_id, s.admission_number, s.first_name, s.last_name,
                       m.id AS medical_id, m.blood_group, m.allergies, m.chronic_conditions,
                       m.current_medication, m.disability_status, m.disability_notes,
                       m.restricted_note, m.is_confidential, m.updated_at
                  FROM students s
                  LEFT JOIN medical_records m ON m.student_id = s.id
                 WHERE s.school_id = ? AND s.status IN (\'active\', \'suspended\')';
        if (!$includeConfidential) {
            $sql .= ' AND (m.id IS NULL OR m.is_confidential = 0)';
        }
        return static::query($sql . ' ORDER BY s.last_name, s.first_name', [$schoolId]);
    }

    public static function medicalRecordForStudent(int $studentId): ?array
    {
        return static::one('SELECT * FROM medical_records WHERE student_id = ?', [$studentId]);
    }

    public static function sickbayVisits(int $schoolId): array
    {
        return static::query(
            'SELECT v.*, s.admission_number, s.first_name, s.last_name,
                    st.first_name AS staff_first_name, st.last_name AS staff_last_name
               FROM sickbay_visits v
               JOIN students s ON s.id = v.student_id AND s.school_id = v.school_id
               LEFT JOIN staff st ON st.id = v.seen_by AND st.school_id = v.school_id
              WHERE v.school_id = ?
              ORDER BY v.visit_date DESC, v.time_in DESC, v.id DESC
              LIMIT 150',
            [$schoolId]
        );
    }

    public static function visit(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM sickbay_visits WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function insertInto(string $table, array $data): int
    {
        if (!in_array($table, ['medical_records', 'sickbay_visits'], true)) {
            throw new InvalidArgumentException('Unsupported health table.');
        }
        $columns = array_keys($data);
        $statement = static::db()->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_fill(0, count($columns), '?')) . ')'
        );
        $statement->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    public static function updateMedical(int $studentId, array $data): void
    {
        $assignments = implode(', ', array_map(
            static fn (string $column): string => $column . ' = ?',
            array_keys($data)
        ));
        $statement = static::db()->prepare('UPDATE medical_records SET ' . $assignments . ' WHERE student_id = ?');
        $statement->execute([...array_values($data), $studentId]);
    }
}
