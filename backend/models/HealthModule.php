<?php
declare(strict_types=1);

/**
 * Medical records and sick bay visits.
 *
 * Backend model: thin static data-access layer over the shared PDO connection.
 */
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

    public static function staffMember(int $id, int $schoolId): ?array
    {
        return static::one('SELECT id FROM staff WHERE id = ? AND school_id = ?', [$id, $schoolId]);
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
        if (!in_array($table, ['medical_records', 'sickbay_visits', 'medication_records', 'medical_incidents', 'counselling_sessions', 'welfare_records'], true)) {
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

    // ------------------------------------------------------------------
    // Medication administration
    // ------------------------------------------------------------------

    public static function staff(int $schoolId): array
    {
        return static::query(
            "SELECT id, first_name, last_name, position
               FROM staff
              WHERE school_id = ? AND employment_status IN ('active','probation','contract')
              ORDER BY last_name, first_name",
            [$schoolId]
        );
    }

    public static function medications(int $schoolId, int $studentId = 0): array
    {
        $sql = 'SELECT m.*, s.admission_number, s.first_name, s.last_name,
                       CONCAT_WS(\' \', st.first_name, st.last_name) AS administered_by_name
                  FROM medication_records m
                  JOIN students s ON s.id = m.student_id AND s.school_id = m.school_id
                  LEFT JOIN staff st ON st.id = m.administered_by AND st.school_id = m.school_id
                 WHERE m.school_id = ?';
        $params = [$schoolId];
        if ($studentId > 0) {
            $sql .= ' AND m.student_id = ?';
            $params[] = $studentId;
        }
        return static::query($sql . ' ORDER BY m.administered_at DESC, m.id DESC LIMIT 200', $params);
    }

    public static function addMedication(array $data): int
    {
        return self::insertInto('medication_records', $data);
    }

    // ------------------------------------------------------------------
    // Medical incidents
    // ------------------------------------------------------------------

    public static function incidents(int $schoolId): array
    {
        return static::query(
            'SELECT i.*, s.admission_number, s.first_name, s.last_name,
                    CONCAT_WS(\' \', st.first_name, st.last_name) AS reported_by_name
               FROM medical_incidents i
               LEFT JOIN students s ON s.id = i.student_id AND s.school_id = i.school_id
               LEFT JOIN staff st ON st.id = i.reported_by AND st.school_id = i.school_id
              WHERE i.school_id = ?
              ORDER BY i.incident_date DESC, i.incident_time DESC, i.id DESC
              LIMIT 200',
            [$schoolId]
        );
    }

    public static function addIncident(array $data): int
    {
        return self::insertInto('medical_incidents', $data);
    }

    public static function setIncidentStatus(int $id, int $schoolId, string $status, int $actorId): bool
    {
        $closedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
        return static::execute(
            'UPDATE medical_incidents SET status = ?, closed_at = ?, closed_by = ? WHERE id = ? AND school_id = ?',
            [$status, $closedAt, $closedAt ? $actorId : null, $id, $schoolId]
        ) > 0;
    }

    // ------------------------------------------------------------------
    // Counselling sessions
    // ------------------------------------------------------------------

    public static function counselling(int $schoolId, int $studentId = 0): array
    {
        $sql = 'SELECT cs.*, s.admission_number, s.first_name, s.last_name,
                       CONCAT_WS(\' \', st.first_name, st.last_name) AS counsellor_name
                  FROM counselling_sessions cs
                  JOIN students s ON s.id = cs.student_id AND s.school_id = cs.school_id
                  LEFT JOIN staff st ON st.id = cs.counsellor_staff_id AND st.school_id = cs.school_id
                 WHERE cs.school_id = ?';
        $params = [$schoolId];
        if ($studentId > 0) {
            $sql .= ' AND cs.student_id = ?';
            $params[] = $studentId;
        }
        return static::query($sql . ' ORDER BY cs.session_date DESC, cs.id DESC LIMIT 200', $params);
    }

    public static function nextSessionNumber(int $studentId, int $schoolId): int
    {
        $current = static::scalar(
            'SELECT COALESCE(MAX(session_number), 0) FROM counselling_sessions WHERE student_id = ? AND school_id = ?',
            [$studentId, $schoolId]
        );
        return (int)$current + 1;
    }

    public static function addCounselling(array $data): int
    {
        return self::insertInto('counselling_sessions', $data);
    }

    // ------------------------------------------------------------------
    // Welfare and support records
    // ------------------------------------------------------------------

    public static function welfare(int $schoolId): array
    {
        return static::query(
            'SELECT w.*, s.admission_number, s.first_name, s.last_name,
                    CONCAT_WS(\' \', st.first_name, st.last_name) AS responsible_name
               FROM welfare_records w
               JOIN students s ON s.id = w.student_id AND s.school_id = w.school_id
               LEFT JOIN staff st ON st.id = w.responsible_staff AND st.school_id = w.school_id
              WHERE w.school_id = ?
              ORDER BY w.date_identified DESC, w.id DESC
              LIMIT 200',
            [$schoolId]
        );
    }

    public static function addWelfare(array $data): int
    {
        return self::insertInto('welfare_records', $data);
    }

    public static function setWelfareStatus(int $id, int $schoolId, string $status): bool
    {
        $closedAt = $status === 'closed' ? date('Y-m-d H:i:s') : null;
        return static::execute(
            'UPDATE welfare_records SET status = ?, closed_at = ? WHERE id = ? AND school_id = ?',
            [$status, $closedAt, $id, $schoolId]
        ) > 0;
    }

    // ------------------------------------------------------------------
    // Dashboard counts
    // ------------------------------------------------------------------

    public static function summary(int $schoolId): array
    {
        $row = static::one(
            "SELECT
                (SELECT COUNT(*) FROM medication_records WHERE school_id = ? AND administered_at >= NOW() - INTERVAL 30 DAY) AS medications_30d,
                (SELECT COUNT(*) FROM medical_incidents WHERE school_id = ? AND status IN ('reported','investigating','escalated')) AS open_incidents,
                (SELECT COUNT(*) FROM counselling_sessions WHERE school_id = ? AND status IN ('scheduled','held','follow_up')) AS active_counselling,
                (SELECT COUNT(*) FROM welfare_records WHERE school_id = ? AND status IN ('open','in_progress')) AS open_welfare",
            [$schoolId, $schoolId, $schoolId, $schoolId]
        );
        return $row ?? ['medications_30d' => 0, 'open_incidents' => 0, 'active_counselling' => 0, 'open_welfare' => 0];
    }
}
