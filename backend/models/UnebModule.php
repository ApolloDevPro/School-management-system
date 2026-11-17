<?php
declare(strict_types=1);

final class UnebModule extends Model
{
    public static function levels(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,code,is_active FROM uneb_levels WHERE school_id = ? ORDER BY name',
            [$schoolId]
        );
    }

    public static function candidates(int $schoolId, ?int $levelId = null, string $status = ''): array
    {
        $sql = 'SELECT c.*, l.name AS level_name,
                       s.admission_number, s.first_name, s.last_name, s.gender, s.date_of_birth
                  FROM uneb_candidates c
                  JOIN uneb_levels l ON l.id = c.level_id
                  JOIN students s ON s.id = c.student_id
                 WHERE c.school_id = ?';
        $params = [$schoolId];
        if ($levelId !== null) {
            $sql .= ' AND c.level_id = ?';
            $params[] = $levelId;
        }
        if ($status !== '') {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY c.index_number, c.id';
        return static::query($sql, $params);
    }

    public static function candidate(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT c.*, l.name AS level_name
               FROM uneb_candidates c
               JOIN uneb_levels l ON l.id = c.level_id
              WHERE c.id = ? AND c.school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function candidateSubjects(int $candidateId): array
    {
        return static::query(
            'SELECT cs.*, sub.code AS subject_code, sub.name AS subject_name
               FROM uneb_candidate_subjects cs
               JOIN subjects sub ON sub.id = cs.subject_id
              WHERE cs.candidate_id = ?
              ORDER BY sub.order_index, sub.name',
            [$candidateId]
        );
    }

    public static function saveCandidate(int $schoolId, array $data, ?int $id = null, int $actorId): int
    {
        if ($id !== null) {
            static::execute(
                'UPDATE uneb_candidates
                    SET index_number = ?, full_name = ?, gender = ?, date_of_birth = ?,
                        student_id = ?, level_id = ?, status = ?, notes = ?
                  WHERE id = ? AND school_id = ?',
                [
                    $data['index_number'], $data['full_name'], $data['gender'],
                    $data['date_of_birth'], $data['student_id'] ?: null,
                    $data['level_id'], $data['status'], $data['notes'] ?: null,
                    $id, $schoolId,
                ]
            );
            return $id;
        }
        static::execute(
            'INSERT INTO uneb_candidates
                (school_id,index_number,full_name,gender,date_of_birth,student_id,level_id,status,notes,registered_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $schoolId, $data['index_number'], $data['full_name'], $data['gender'],
                $data['date_of_birth'], $data['student_id'] ?: null, $data['level_id'],
                $data['status'], $data['notes'] ?: null, $actorId,
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function saveCandidateSubjects(int $candidateId, array $subjectIds): void
    {
        static::execute('DELETE FROM uneb_candidate_subjects WHERE candidate_id = ?', [$candidateId]);
        foreach ($subjectIds as $subjectId) {
            static::execute(
                'INSERT IGNORE INTO uneb_candidate_subjects (candidate_id, subject_id) VALUES (?,?)',
                [$candidateId, (int)$subjectId]
            );
        }
    }

    public static function deleteCandidate(int $id, int $schoolId): bool
    {
        return static::execute('DELETE FROM uneb_candidates WHERE id = ? AND school_id = ?', [$id, $schoolId]) > 0;
    }

    // ------------------------------------------------- Continuous Assessment

    public static function casubmissions(int $schoolId, ?int $levelId = null): array
    {
        $sql = 'SELECT cs.*, l.name AS level_name,
                       COUNT(ccs.id) AS subject_count
                  FROM uneb_ca_submission cs
                  JOIN uneb_levels l ON l.id = cs.level_id
                  LEFT JOIN uneb_ca_subject_scores ccs ON ccs.submission_id = cs.id
                 WHERE cs.school_id = ?';
        $params = [$schoolId];
        if ($levelId !== null) {
            $sql .= ' AND cs.level_id = ?';
            $params[] = $levelId;
        }
        $sql .= ' GROUP BY cs.id ORDER BY cs.submitted_at DESC';
        return static::query($sql, $params);
    }

    public static function casubmission(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT cs.*, l.name AS level_name
               FROM uneb_ca_submission cs
               JOIN uneb_levels l ON l.id = cs.level_id
              WHERE cs.id = ? AND cs.school_id = ?',
            [$id, $schoolId]
        );
    }

    public static function casubmissionScores(int $submissionId): array
    {
        return static::query(
            'SELECT ccs.*, c.index_number, c.full_name, sub.code AS subject_code, sub.name AS subject_name
               FROM uneb_ca_subject_scores ccs
               JOIN uneb_candidates c ON c.id = ccs.candidate_id
               JOIN subjects sub ON sub.id = ccs.subject_id
              WHERE ccs.submission_id = ?
              ORDER BY c.index_number, sub.order_index',
            [$submissionId]
        );
    }

    public static function saveCasubmission(int $schoolId, array $data, int $actorId): int
    {
        static::execute(
            'INSERT INTO uneb_ca_submission
                (school_id,level_id,academic_year_id,term_id,submission_type,status,notes,submitted_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $schoolId, $data['level_id'], $data['academic_year_id'], $data['term_id'],
                $data['submission_type'], $data['status'], $data['notes'] ?: null, $actorId,
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function saveCaScores(int $submissionId, array $scores): void
    {
        foreach ($scores as $candidateId => $subjectScores) {
            foreach ($subjectScores as $subjectId => $score) {
                static::execute(
                    'INSERT INTO uneb_ca_subject_scores (submission_id,candidate_id,subject_id,ca_score,project_score,overall_score)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE ca_score = VALUES(ca_score), project_score = VALUES(project_score), overall_score = VALUES(overall_score)',
                    [
                        $submissionId, (int)$candidateId, (int)$subjectId,
                        $score['ca'] ?? null, $score['project'] ?? null,
                        $score['overall'] ?? null,
                    ]
                );
            }
        }
    }

    public static function markCasubmitted(int $id, int $schoolId): bool
    {
        return static::execute(
            'UPDATE uneb_ca_submission SET status = \'submitted\', submitted_at = NOW() WHERE id = ? AND school_id = ? AND status <> \'submitted\'',
            [$id, $schoolId]
        ) > 0;
    }

    // ------------------------------------------------- National Results

    public static function results(int $schoolId, ?int $levelId = null, int $year = 0): array
    {
        $sql = 'SELECT r.*, l.name AS level_name, y.name AS year_name
                  FROM uneb_results r
                  JOIN uneb_levels l ON l.id = r.level_id
                  LEFT JOIN academic_years y ON y.id = r.academic_year_id
                 WHERE r.school_id = ?';
        $params = [$schoolId];
        if ($levelId !== null) {
            $sql .= ' AND r.level_id = ?';
            $params[] = $levelId;
        }
        if ($year > 0) {
            $sql .= ' AND r.exam_year = ?';
            $params[] = $year;
        }
        $sql .= ' ORDER BY r.exam_year DESC, r.index_number';
        return static::query($sql, $params);
    }

    public static function saveResult(int $schoolId, array $data, int $actorId): int
    {
        static::execute(
            'INSERT INTO uneb_results
                (school_id,level_id,academic_year_id,exam_year,index_number,full_name,gender,
                 subject_results,overall_level,result_code,ca_score_submitted,notes,recorded_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $schoolId, $data['level_id'], $data['academic_year_id'], $data['exam_year'],
                $data['index_number'], $data['full_name'], $data['gender'],
                $data['subject_results'] ?: null, $data['overall_level'], $data['result_code'],
                $data['ca_score_submitted'] ? 1 : 0, $data['notes'] ?: null, $actorId,
            ]
        );
        return (int)static::db()->lastInsertId();
    }

    public static function stats(int $schoolId): array
    {
        $row = static::one(
            'SELECT
                (SELECT COUNT(*) FROM uneb_candidates WHERE school_id = ?) AS total_candidates,
                (SELECT COUNT(*) FROM uneb_candidates WHERE school_id = ? AND status = \'registered\') AS registered,
                (SELECT COUNT(*) FROM uneb_ca_submission WHERE school_id = ? AND status = \'submitted\') AS casubmitted,
                (SELECT COUNT(*) FROM uneb_results WHERE school_id = ?) AS results_recorded',
            [$schoolId, $schoolId, $schoolId, $schoolId]
        );
        return $row ?: [];
    }
}
