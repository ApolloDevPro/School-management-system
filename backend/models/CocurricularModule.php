<?php
declare(strict_types=1);

/**
 * Clubs, sports teams, fixtures, competitions, talent and house points.
 *
 * Backend model: static data-access layer over the shared PDO connection.
 * Every lookup is limited to the signed-in user's school.
 */
final class CocurricularModule extends Model
{
    /**
     * Run several writes as one unit and roll back on any error.
     */
    private static function transaction(callable $callback): mixed
    {
        $db = static::db();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    // ------------------------------------------------------------------
    // Clubs
    // ------------------------------------------------------------------

    public static function clubs(int $schoolId): array
    {
        return static::query(
            'SELECT id,name,club_category,meeting_day,meeting_venue,status FROM clubs WHERE school_id=? ORDER BY name LIMIT 300',
            [$schoolId]
        );
    }

    public static function createClub(array $data): int
    {
        static::db()->prepare(
            'INSERT INTO clubs (school_id,name,club_category,description,status) VALUES (?,?,?,?,?)'
        )->execute([$data['school_id'],$data['name'],$data['category'],$data['description'],'active']);
        return (int)static::db()->lastInsertId();
    }

    public static function changeStatus(int $id, int $schoolId, string $status): bool
    {
        return static::execute('UPDATE clubs SET status=? WHERE id=? AND school_id=?',[$status,$id,$schoolId]) > 0;
    }

    // ------------------------------------------------------------------
    // Reference lists used by the forms
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

    public static function houses(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, colour FROM houses WHERE school_id = ? AND is_active = 1 ORDER BY name',
            [$schoolId]
        );
    }

    public static function staffMember(int $id, int $schoolId): ?array
    {
        return static::one('SELECT id FROM staff WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function studentExists(int $id, int $schoolId): ?array
    {
        return static::one('SELECT id FROM students WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function club(int $id, int $schoolId): ?array
    {
        return static::one('SELECT id FROM clubs WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function years(int $schoolId): array
    {
        return static::query(
            'SELECT id, name, is_active FROM academic_years WHERE school_id = ? ORDER BY start_date DESC',
            [$schoolId]
        );
    }

    public static function terms(int $schoolId): array
    {
        return static::query(
            'SELECT t.id, t.name, t.term_number, t.academic_year_id, t.is_active, y.name AS year_name
               FROM terms t JOIN academic_years y ON y.id = t.academic_year_id
              WHERE y.school_id = ?
              ORDER BY y.start_date DESC, t.term_number',
            [$schoolId]
        );
    }

    // ------------------------------------------------------------------
    // Sports teams and members
    // ------------------------------------------------------------------

    public static function teams(int $schoolId): array
    {
        return static::query(
            "SELECT t.id, t.team_name, t.sport_category, t.team_type, t.age_group,
                    t.season, t.training_day, t.training_time, t.training_venue, t.status,
                    t.coach_staff_id, t.assistant_coach_staff_id, t.academic_year_id,
                    y.name AS year_name,
                    CONCAT_WS(' ', cs.first_name, cs.last_name) AS coach_name,
                    (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id AND m.status = 'active') AS member_count
               FROM sports_teams t
               LEFT JOIN academic_years y ON y.id = t.academic_year_id
               LEFT JOIN staff cs ON cs.id = t.coach_staff_id
              WHERE t.school_id = ?
              ORDER BY t.sport_category, t.team_name",
            [$schoolId]
        );
    }

    public static function team(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT * FROM sports_teams WHERE id = ? AND school_id = ?',
            [$id, $schoolId]
        );
    }

    /**
     * Create or update a team. Passing an id updates that team only.
     */
    public static function saveTeam(int $schoolId, array $data, int $id = 0): int
    {
        if ($id > 0) {
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            $statement = static::db()->prepare(
                'UPDATE sports_teams SET ' . $assignments . ' WHERE id = ? AND school_id = ?'
            );
            $statement->execute([...array_values($data), $id, $schoolId]);
            if ($statement->rowCount() === 0 && !static::team($id, $schoolId)) {
                throw new DomainException('Team not found in this school.');
            }
            return $id;
        }
        $data['school_id'] = $schoolId;
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO sports_teams (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    public static function teamMembers(int $schoolId, int $teamId = 0): array
    {
        $sql = "SELECT m.id, m.team_id, m.student_id, m.member_role, m.shirt_number,
                       m.joined_date, m.status, m.remarks,
                       t.team_name, s.admission_number, s.first_name, s.last_name,
                       c.name AS class_name
                  FROM team_members m
                  JOIN sports_teams t ON t.id = m.team_id AND t.school_id = ?
                  JOIN students s ON s.id = m.student_id
                  LEFT JOIN classes c ON c.id = s.current_class_id";
        $params = [$schoolId];
        if ($teamId > 0) {
            $sql .= ' WHERE m.team_id = ?';
            $params[] = $teamId;
        }
        return static::query($sql . ' ORDER BY t.team_name, s.last_name, s.first_name LIMIT 500', $params);
    }

    public static function addMember(int $schoolId, array $data): int
    {
        if (!static::team((int)$data['team_id'], $schoolId)) {
            throw new DomainException('Choose a team from this school.');
        }
        try {
            $columns = array_keys($data);
            static::db()->prepare(
                'INSERT INTO team_members (' . implode(',', $columns) . ') VALUES ('
                . implode(',', array_fill(0, count($columns), '?')) . ')'
            )->execute(array_values($data));
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                throw new DomainException('That student is already on this team.');
            }
            throw $error;
        }
        return (int)static::db()->lastInsertId();
    }

    public static function removeMember(int $id, int $schoolId): bool
    {
        return static::execute(
            'DELETE m FROM team_members m
               JOIN sports_teams t ON t.id = m.team_id
              WHERE m.id = ? AND t.school_id = ?',
            [$id, $schoolId]
        ) > 0;
    }

    // ------------------------------------------------------------------
    // Fixtures and results
    // ------------------------------------------------------------------

    public static function fixtures(int $schoolId, int $teamId = 0): array
    {
        $sql = "SELECT f.id, f.team_id, f.opponent, f.opponent_type, f.venue, f.home_away,
                       f.fixture_date, f.start_time, f.competition_id, f.category,
                       f.transport_required, f.status, f.notes,
                       t.team_name, t.sport_category,
                       r.our_score, r.opponent_score, r.result_outcome, r.half_time_score,
                       r.mvp_student_id, r.scorers, r.referee, r.attendance_count
                  FROM fixtures f
                  JOIN sports_teams t ON t.id = f.team_id AND t.school_id = ?
                  LEFT JOIN fixture_results r ON r.fixture_id = f.id";
        $params = [$schoolId];
        if ($teamId > 0) {
            $sql .= ' WHERE f.team_id = ?';
            $params[] = $teamId;
        }
        return static::query($sql . ' ORDER BY f.fixture_date DESC, f.start_time LIMIT 200', $params);
    }

    public static function fixture(int $id, int $schoolId): ?array
    {
        return static::one(
            'SELECT f.* FROM fixtures f
               JOIN sports_teams t ON t.id = f.team_id AND t.school_id = ?
              WHERE f.id = ?',
            [$schoolId, $id]
        );
    }

    public static function saveFixture(int $schoolId, array $data, int $id = 0): int
    {
        if (!static::team((int)$data['team_id'], $schoolId)) {
            throw new DomainException('Choose a team from this school.');
        }
        if ($id > 0) {
            if (!static::fixture($id, $schoolId)) {
                throw new DomainException('Fixture not found in this school.');
            }
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            static::db()->prepare('UPDATE fixtures SET ' . $assignments . ' WHERE id = ?')
                ->execute([...array_values($data), $id]);
            return $id;
        }
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO fixtures (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    /**
     * Record or correct the result of a played fixture.
     *
     * The win/draw/loss outcome is derived from the two scores, and the
     * fixture is marked as played in the same transaction.
     */
    public static function saveResult(int $schoolId, array $data): int
    {
        $fixtureId = (int)$data['fixture_id'];
        if (!static::fixture($fixtureId, $schoolId)) {
            throw new DomainException('Fixture not found in this school.');
        }
        $data['result_outcome'] = $data['our_score'] > $data['opponent_score'] ? 'win'
            : ($data['our_score'] === $data['opponent_score'] ? 'draw' : 'loss');

        return self::transaction(static function (PDO $db) use ($fixtureId, $data): int {
            $existing = $db->prepare('SELECT id FROM fixture_results WHERE fixture_id = ?');
            $existing->execute([$fixtureId]);
            $row = $existing->fetch();
            if ($row) {
                $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
                $db->prepare('UPDATE fixture_results SET ' . $assignments . ', recorded_at = NOW() WHERE id = ?')
                    ->execute([...array_values($data), (int)$row['id']]);
                $resultId = (int)$row['id'];
            } else {
                $columns = array_keys($data);
                $db->prepare(
                    'INSERT INTO fixture_results (' . implode(',', $columns) . ') VALUES ('
                    . implode(',', array_fill(0, count($columns), '?')) . ')'
                )->execute(array_values($data));
                $resultId = (int)$db->lastInsertId();
            }
            $db->prepare("UPDATE fixtures SET status = 'played' WHERE id = ?")->execute([$fixtureId]);
            return $resultId;
        });
    }

    // ------------------------------------------------------------------
    // Competitions and entries
    // ------------------------------------------------------------------

    public static function competitions(int $schoolId): array
    {
        return static::query(
            "SELECT c.*, (SELECT COUNT(*) FROM competition_entries e WHERE e.competition_id = c.id) AS entry_count
               FROM competitions c
              WHERE c.school_id = ?
              ORDER BY c.start_date DESC, c.id DESC
              LIMIT 200",
            [$schoolId]
        );
    }

    public static function competition(int $id, int $schoolId): ?array
    {
        return static::one('SELECT * FROM competitions WHERE id = ? AND school_id = ?', [$id, $schoolId]);
    }

    public static function saveCompetition(int $schoolId, array $data, int $id = 0): int
    {
        if ($id > 0) {
            if (!static::competition($id, $schoolId)) {
                throw new DomainException('Competition not found in this school.');
            }
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            static::db()->prepare('UPDATE competitions SET ' . $assignments . ' WHERE id = ? AND school_id = ?')
                ->execute([...array_values($data), $id, $schoolId]);
            return $id;
        }
        $data['school_id'] = $schoolId;
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO competitions (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    public static function entries(int $schoolId, int $competitionId = 0): array
    {
        $sql = "SELECT e.*, c.name AS competition_name, c.competition_type,
                       s.admission_number, s.first_name, s.last_name,
                       t.team_name, cl.name AS club_name
                  FROM competition_entries e
                  JOIN competitions c ON c.id = e.competition_id AND c.school_id = ?
                  LEFT JOIN students s ON s.id = e.student_id
                  LEFT JOIN sports_teams t ON t.id = e.team_id
                  LEFT JOIN clubs cl ON cl.id = e.club_id";
        $params = [$schoolId];
        if ($competitionId > 0) {
            $sql .= ' WHERE e.competition_id = ?';
            $params[] = $competitionId;
        }
        return static::query($sql . ' ORDER BY c.start_date DESC, e.id DESC LIMIT 400', $params);
    }

    public static function saveEntry(int $schoolId, array $data): int
    {
        if (!static::competition((int)$data['competition_id'], $schoolId)) {
            throw new DomainException('Choose a competition from this school.');
        }
        try {
            $columns = array_keys($data);
            static::db()->prepare(
                'INSERT INTO competition_entries (' . implode(',', $columns) . ') VALUES ('
                . implode(',', array_fill(0, count($columns), '?')) . ')'
            )->execute(array_values($data));
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                throw new DomainException('That student is already entered for this competition.');
            }
            throw $error;
        }
        return (int)static::db()->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Talent records
    // ------------------------------------------------------------------

    public static function talents(int $schoolId, int $studentId = 0): array
    {
        $sql = "SELECT tr.*, s.admission_number, s.first_name, s.last_name, c.name AS class_name
                  FROM talent_records tr
                  JOIN students s ON s.id = tr.student_id AND s.school_id = ?
                  LEFT JOIN classes c ON c.id = s.current_class_id";
        $params = [$schoolId];
        if ($studentId > 0) {
            $sql .= ' WHERE tr.student_id = ?';
            $params[] = $studentId;
        }
        return static::query($sql . ' ORDER BY tr.date_recorded DESC, tr.id DESC LIMIT 300', $params);
    }

    public static function saveTalent(int $schoolId, array $data, int $id = 0): int
    {
        if ($id > 0) {
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
            $statement = static::db()->prepare(
                'UPDATE talent_records tr
                   JOIN students s ON s.id = tr.student_id
                    SET ' . $assignments . '
                  WHERE tr.id = ? AND s.school_id = ?'
            );
            $statement->execute([...array_values($data), $id, $schoolId]);
            return $id;
        }
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO talent_records (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }

    // ------------------------------------------------------------------
    // House competition points
    // ------------------------------------------------------------------

    public static function housePoints(int $schoolId, int $yearId = 0, int $termId = 0): array
    {
        $sql = "SELECT p.*, h.name AS house_name, y.name AS year_name, t.name AS term_name
                  FROM house_competition_points p
                  JOIN houses h ON h.id = p.house_id AND h.school_id = ?
                  JOIN academic_years y ON y.id = p.academic_year_id
                  LEFT JOIN terms t ON t.id = p.term_id
                 WHERE 1 = 1";
        $params = [$schoolId];
        if ($yearId > 0) {
            $sql .= ' AND p.academic_year_id = ?';
            $params[] = $yearId;
        }
        if ($termId > 0) {
            $sql .= ' AND p.term_id = ?';
            $params[] = $termId;
        }
        return static::query($sql . ' ORDER BY p.event_date DESC, p.id DESC LIMIT 300', $params);
    }

    /**
     * Current standings: awards add to the total and penalties subtract.
     */
    public static function houseTotals(int $schoolId, int $yearId = 0, int $termId = 0): array
    {
        $sql = "SELECT h.id, h.name, h.colour,
                       COALESCE(SUM(CASE WHEN p.points_type = 'penalty' THEN -p.points ELSE p.points END), 0) AS total,
                       COUNT(p.id) AS events
                  FROM houses h
                  LEFT JOIN house_competition_points p ON p.house_id = h.id";
        $params = [];
        if ($yearId > 0) {
            $sql .= ' AND p.academic_year_id = ?';
            $params[] = $yearId;
        }
        if ($termId > 0) {
            $sql .= ' AND p.term_id = ?';
            $params[] = $termId;
        }
        $sql .= ' WHERE h.school_id = ? AND h.is_active = 1 GROUP BY h.id, h.name, h.colour ORDER BY total DESC, h.name';
        $params[] = $schoolId;
        return static::query($sql, $params);
    }

    public static function saveHousePoints(int $schoolId, array $data): int
    {
        $house = static::one(
            'SELECT id FROM houses WHERE id = ? AND school_id = ?',
            [(int)$data['house_id'], $schoolId]
        );
        if (!$house) {
            throw new DomainException('Choose a house from this school.');
        }
        $year = static::one(
            'SELECT id FROM academic_years WHERE id = ? AND school_id = ?',
            [(int)$data['academic_year_id'], $schoolId]
        );
        if (!$year) {
            throw new DomainException('Choose an academic year from this school.');
        }
        if (!empty($data['term_id'])) {
            $term = static::one(
                'SELECT id FROM terms WHERE id = ? AND academic_year_id = ?',
                [(int)$data['term_id'], (int)$data['academic_year_id']]
            );
            if (!$term) {
                throw new DomainException('The term must belong to the selected academic year.');
            }
        }
        $columns = array_keys($data);
        static::db()->prepare(
            'INSERT INTO house_competition_points (' . implode(',', $columns) . ') VALUES ('
            . implode(',', array_fill(0, count($columns), '?')) . ')'
        )->execute(array_values($data));
        return (int)static::db()->lastInsertId();
    }
}
