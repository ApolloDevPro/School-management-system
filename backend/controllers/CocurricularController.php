<?php
declare(strict_types=1);

/**
 * Clubs, sports teams, fixtures, competitions, talent and house points.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class CocurricularController extends Controller
{
    private const TABS = ['clubs', 'teams', 'fixtures', 'competitions', 'talent', 'houses'];
    private const SPORTS = ['football', 'netball', 'basketball', 'athletics', 'volleyball', 'cricket', 'handball', 'swimming', 'other'];
    private const TEAM_TYPES = ['boys', 'girls', 'mixed'];
    private const AGE_GROUPS = ['junior', 'senior', 'mixed'];
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    private const MEMBER_ROLES = ['player', 'captain', 'vice_captain', 'substitute', 'manager'];
    private const MEMBER_STATUSES = ['active', 'injured', 'dropped', 'left'];
    private const OPPONENT_TYPES = ['inter_school', 'house', 'national', 'friendly', 'tournament'];
    private const HOME_AWAY = ['home', 'away', 'neutral'];
    private const FIXTURE_CATEGORIES = ['league', 'friendly', 'cup', 'knockout', 'house', 'training'];
    private const FIXTURE_STATUSES = ['scheduled', 'live', 'played', 'postponed', 'cancelled', 'abandoned'];
    private const COMPETITION_TYPES = ['music', 'drama', 'science_fair', 'sports', 'debate', 'quiz', 'agriculture', 'ict', 'other'];
    private const LEVELS = ['class', 'school', 'district', 'regional', 'national', 'international'];
    private const COMPETITION_STATUSES = ['planned', 'registration_open', 'participating', 'completed', 'cancelled'];
    private const ENTRY_ROLES = ['solo', 'team_member', 'team_leader', 'support', 'exhibitor'];
    private const ENTRY_STATUSES = ['registered', 'confirmed', 'participated', 'withdrew', 'disqualified'];
    private const TALENT_TYPES = ['music', 'sport', 'drama', 'art', 'leadership', 'academics', 'crafts', 'other'];
    private const HOUSE_CATEGORIES = ['sports', 'academics', 'discipline', 'cultural', 'cleanliness', 'cooking', 'other'];

    public function index(): void
    {
        $schoolId = school_id();
        $tab = (string)($_GET['tab'] ?? 'clubs');
        $yearId = (int)($_GET['year_id'] ?? 0);
        if ($yearId === 0) {
            foreach (CocurricularModule::years($schoolId) as $year) {
                if ((int)$year['is_active'] === 1) {
                    $yearId = (int)$year['id'];
                    break;
                }
            }
        }
        $termId = (int)($_GET['term_id'] ?? 0);

        $this->render('cocurricular/index', [
            'pageTitle' => 'Co-curricular activities',
            'activeNav' => 'cocurricular',
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'clubs',
            'clubs' => CocurricularModule::clubs($schoolId),
            'staff' => CocurricularModule::staff($schoolId),
            'students' => CocurricularModule::students($schoolId),
            'houses' => CocurricularModule::houses($schoolId),
            'years' => CocurricularModule::years($schoolId),
            'terms' => CocurricularModule::terms($schoolId),
            'teams' => CocurricularModule::teams($schoolId),
            'members' => CocurricularModule::teamMembers($schoolId),
            'competitions' => CocurricularModule::competitions($schoolId),
            'fixtures' => CocurricularModule::fixtures($schoolId),
            'entries' => CocurricularModule::entries($schoolId),
            'talents' => CocurricularModule::talents($schoolId),
            'housePoints' => CocurricularModule::housePoints($schoolId, $yearId, $termId),
            'houseTotals' => CocurricularModule::houseTotals($schoolId, $yearId, $termId),
            'yearId' => $yearId,
            'termId' => $termId,
            'canAdd' => Auth::can('cocurricular', 'add'),
            'canEdit' => Auth::can('cocurricular', 'edit'),
        ]);
    }

    // ------------------------------------------------------------------
    // Clubs
    // ------------------------------------------------------------------

    public function storeClub(): void
    {
        $name        = trim((string)($_POST['name'] ?? ''));
        $category    = (string)($_POST['club_category'] ?? 'general');
        $description = trim((string)($_POST['description'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160) { $errors['name'] = 'Enter a club name (max 160 chars).'; }
        if (!in_array($category, ['academic','sports','arts','debate','science','religious','community','general'], true)) {
            $category = 'general';
        }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'cocurricular', ['tab' => 'clubs'], $errors);
        }

        $id = CocurricularModule::createClub([
            'school_id'   => school_id(),
            'name'        => $name,
            'category'    => $category,
            'description' => $description ?: null,
        ]);

        audit('cocurricular', 'club_created', 'clubs', $id, 'Created club: ' . $name);
        $this->respond(true, 'Club "' . $name . '" created.', 'cocurricular', ['tab' => 'clubs']);
    }

    public function toggleClub(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['active', 'inactive'], true)) {
            $this->respond(false, 'Invalid status.', 'cocurricular', ['tab' => 'clubs']);
        }
        $ok = CocurricularModule::changeStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Club status updated.' : 'Could not update club.', 'cocurricular', ['tab' => 'clubs']);
    }

    // ------------------------------------------------------------------
    // Teams and members
    // ------------------------------------------------------------------

    public function saveTeam(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $schoolId = school_id();
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['team_name'] ?? ''));
        $sport = (string)($_POST['sport_category'] ?? 'football');
        $teamType = (string)($_POST['team_type'] ?? 'boys');
        $ageGroup = (string)($_POST['age_group'] ?? '');
        $day = (string)($_POST['training_day'] ?? '');
        $status = (string)($_POST['status'] ?? 'active');
        if ($name === '' || mb_strlen($name) > 140
            || !in_array($sport, self::SPORTS, true)
            || !in_array($teamType, self::TEAM_TYPES, true)
            || ($ageGroup !== '' && !in_array($ageGroup, self::AGE_GROUPS, true))
            || ($day !== '' && !in_array($day, self::DAYS, true))
            || !in_array($status, ['active', 'inactive', 'archived'], true)) {
            $this->respond(false, 'Enter a team name and choose valid sport, type and status options.', 'cocurricular', ['tab' => 'teams']);
        }
        $coachIds = [];
        foreach (['coach_staff_id', 'assistant_coach_staff_id'] as $field) {
            $staffId = (int)($_POST[$field] ?? 0);
            if ($staffId > 0 && !CocurricularModule::staffMember($staffId, $schoolId)) {
                $this->respond(false, 'Choose coaches from this school.', 'cocurricular', ['tab' => 'teams']);
            }
            $coachIds[$field] = $staffId > 0 ? $staffId : null;
        }
        $yearId = (int)($_POST['academic_year_id'] ?? 0);
        $data = [
            'team_name' => $name,
            'sport_category' => $sport,
            'team_type' => $teamType,
            'age_group' => $ageGroup !== '' ? $ageGroup : null,
            'coach_staff_id' => $coachIds['coach_staff_id'],
            'assistant_coach_staff_id' => $coachIds['assistant_coach_staff_id'],
            'season' => $this->line($_POST['season'] ?? '', 30),
            'academic_year_id' => $yearId > 0 ? $yearId : null,
            'training_day' => $day !== '' ? $day : null,
            'training_time' => $this->timeOrNull($_POST['training_time'] ?? ''),
            'training_venue' => $this->line($_POST['training_venue'] ?? '', 120),
            'status' => $status,
        ];
        try {
            $teamId = CocurricularModule::saveTeam($schoolId, $data, $id);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'teams']);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'A team with that name and season already exists.', 'cocurricular', ['tab' => 'teams']);
            }
            throw $error;
        }
        audit('cocurricular', $id > 0 ? 'update' : 'create', 'sports_teams', $teamId, 'Saved sports team ' . $name);
        $this->respond(true, 'Team saved.', 'cocurricular', ['tab' => 'teams']);
    }

    public function addMember(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $schoolId = school_id();
        $teamId = (int)($_POST['team_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $role = (string)($_POST['member_role'] ?? 'player');
        if (!CocurricularModule::team($teamId, $schoolId) || !CocurricularModule::studentExists($studentId, $schoolId)
            || !in_array($role, self::MEMBER_ROLES, true)) {
            $this->respond(false, 'Choose a team, student and role from this school.', 'cocurricular', ['tab' => 'teams']);
        }
        $shirt = trim((string)($_POST['shirt_number'] ?? ''));
        $data = [
            'team_id' => $teamId,
            'student_id' => $studentId,
            'member_role' => $role,
            'shirt_number' => $shirt !== '' && is_numeric($shirt) && (int)$shirt > 0 ? (int)$shirt : null,
            'joined_date' => $this->dateOrNull($_POST['joined_date'] ?? '') ?? date('Y-m-d'),
            'status' => in_array($_POST['status'] ?? '', self::MEMBER_STATUSES, true) ? (string)$_POST['status'] : 'active',
            'remarks' => $this->line($_POST['remarks'] ?? '', 255),
        ];
        try {
            $memberId = CocurricularModule::addMember($schoolId, $data);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'teams']);
        }
        audit('cocurricular', 'create', 'team_members', $memberId, 'Added student #' . $studentId . ' to team #' . $teamId);
        $this->respond(true, 'Player added to the team.', 'cocurricular', ['tab' => 'teams']);
    }

    public function removeMember(): void
    {
        if (!$this->requirePermission('edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $ok = CocurricularModule::removeMember($id, school_id());
        if ($ok) {
            audit('cocurricular', 'delete', 'team_members', $id, 'Removed team member #' . $id);
        }
        $this->respond($ok, $ok ? 'Player removed from the team.' : 'Member not found.', 'cocurricular', ['tab' => 'teams']);
    }

    // ------------------------------------------------------------------
    // Fixtures and results
    // ------------------------------------------------------------------

    public function saveFixture(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $schoolId = school_id();
        $id = (int)($_POST['id'] ?? 0);
        $teamId = (int)($_POST['team_id'] ?? 0);
        $opponent = trim((string)($_POST['opponent'] ?? ''));
        $date = $this->dateOrNull($_POST['fixture_date'] ?? '');
        $opponentType = (string)($_POST['opponent_type'] ?? 'inter_school');
        $homeAway = (string)($_POST['home_away'] ?? 'home');
        $category = (string)($_POST['category'] ?? 'friendly');
        $status = (string)($_POST['status'] ?? 'scheduled');
        if ($opponent === '' || mb_strlen($opponent) > 160 || $date === null
            || !in_array($opponentType, self::OPPONENT_TYPES, true)
            || !in_array($homeAway, self::HOME_AWAY, true)
            || !in_array($category, self::FIXTURE_CATEGORIES, true)
            || !in_array($status, self::FIXTURE_STATUSES, true)) {
            $this->respond(false, 'Choose a team and enter the opponent and fixture date.', 'cocurricular', ['tab' => 'fixtures']);
        }
        $competitionId = (int)($_POST['competition_id'] ?? 0);
        if ($competitionId > 0 && !CocurricularModule::competition($competitionId, $schoolId)) {
            $this->respond(false, 'Choose a competition from this school.', 'cocurricular', ['tab' => 'fixtures']);
        }
        $data = [
            'team_id' => $teamId,
            'opponent' => $opponent,
            'opponent_type' => $opponentType,
            'venue' => $this->line($_POST['venue'] ?? '', 160),
            'home_away' => $homeAway,
            'fixture_date' => $date,
            'start_time' => $this->timeOrNull($_POST['start_time'] ?? ''),
            'competition_id' => $competitionId > 0 ? $competitionId : null,
            'category' => $category,
            'transport_required' => isset($_POST['transport_required']) ? 1 : 0,
            'status' => $status,
            'notes' => $this->line($_POST['notes'] ?? '', 255),
        ];
        try {
            if ($id === 0) {
                $data['created_by'] = (int)Auth::user()['id'];
            }
            $fixtureId = CocurricularModule::saveFixture($schoolId, $data, $id);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'fixtures']);
        }
        audit('cocurricular', $id > 0 ? 'update' : 'create', 'fixtures', $fixtureId, 'Saved fixture vs ' . $opponent);
        $this->respond(true, 'Fixture saved.', 'cocurricular', ['tab' => 'fixtures']);
    }

    public function saveResult(): void
    {
        if (!$this->requirePermission('edit')) {
            return;
        }
        $schoolId = school_id();
        $fixtureId = (int)($_POST['fixture_id'] ?? 0);
        $our = filter_var($_POST['our_score'] ?? null, FILTER_VALIDATE_INT);
        $theirs = filter_var($_POST['opponent_score'] ?? null, FILTER_VALIDATE_INT);
        if ($our === false || $theirs === false || $our < 0 || $theirs < 0 || $our > 999 || $theirs > 999) {
            $this->respond(false, 'Enter both scores as whole numbers.', 'cocurricular', ['tab' => 'fixtures']);
        }
        $mvp = (int)($_POST['mvp_student_id'] ?? 0);
        if ($mvp > 0 && !CocurricularModule::studentExists($mvp, $schoolId)) {
            $this->respond(false, 'Choose the MVP from this school.', 'cocurricular', ['tab' => 'fixtures']);
        }
        $attendance = trim((string)($_POST['attendance_count'] ?? ''));
        $data = [
            'fixture_id' => $fixtureId,
            'our_score' => $our,
            'opponent_score' => $theirs,
            'half_time_score' => $this->line($_POST['half_time_score'] ?? '', 20),
            'mvp_student_id' => $mvp > 0 ? $mvp : null,
            'scorers' => $this->text($_POST['scorers'] ?? ''),
            'team_of_the_day' => $this->text($_POST['team_of_the_day'] ?? ''),
            'referee' => $this->line($_POST['referee'] ?? '', 140),
            'attendance_count' => $attendance !== '' && is_numeric($attendance) && (int)$attendance >= 0 ? (int)$attendance : null,
            'remarks' => $this->line($_POST['remarks'] ?? '', 500),
            'recorded_by' => (int)Auth::user()['id'] ?? null,
        ];
        try {
            $resultId = CocurricularModule::saveResult($schoolId, $data);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'fixtures']);
        }
        $outcome = $our > $theirs ? 'a win' : ($our === $theirs ? 'a draw' : 'a loss');
        audit('cocurricular', 'create', 'fixture_results', $resultId, 'Recorded fixture result ' . $our . '-' . $theirs . ' (' . $outcome . ')');
        $this->respond(true, 'Result recorded: ' . $outcome . ' (' . $our . '–' . $theirs . ').', 'cocurricular', ['tab' => 'fixtures']);
    }

    // ------------------------------------------------------------------
    // Competitions and entries
    // ------------------------------------------------------------------

    public function saveCompetition(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['competition_type'] ?? 'sports');
        $level = (string)($_POST['level'] ?? 'school');
        $status = (string)($_POST['status'] ?? 'planned');
        if ($name === '' || mb_strlen($name) > 180
            || !in_array($type, self::COMPETITION_TYPES, true)
            || !in_array($level, self::LEVELS, true)
            || !in_array($status, self::COMPETITION_STATUSES, true)) {
            $this->respond(false, 'Enter a competition name and choose valid type, level and status.', 'cocurricular', ['tab' => 'competitions']);
        }
        $budget = null;
        if (($_POST['budget'] ?? '') !== '') {
            if (!is_numeric($_POST['budget']) || (float)$_POST['budget'] < 0) {
                $this->respond(false, 'Enter a valid budget or leave it blank.', 'cocurricular', ['tab' => 'competitions']);
            }
            $budget = round((float)$_POST['budget'], 2);
        }
        $data = [
            'name' => $name,
            'competition_type' => $type,
            'level' => $level,
            'organiser' => $this->line($_POST['organiser'] ?? '', 160),
            'venue' => $this->line($_POST['venue'] ?? '', 160),
            'start_date' => $this->dateOrNull($_POST['start_date'] ?? ''),
            'end_date' => $this->dateOrNull($_POST['end_date'] ?? ''),
            'budget' => $budget,
            'transport_required' => isset($_POST['transport_required']) ? 1 : 0,
            'status' => $status,
            'results_summary' => $this->text($_POST['results_summary'] ?? ''),
        ];
        try {
            if ($id === 0) {
                $data['created_by'] = (int)Auth::user()['id'];
            }
            $competitionId = CocurricularModule::saveCompetition(school_id(), $data, $id);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'competitions']);
        }
        audit('cocurricular', $id > 0 ? 'update' : 'create', 'competitions', $competitionId, 'Saved competition ' . $name);
        $this->respond(true, 'Competition saved.', 'cocurricular', ['tab' => 'competitions']);
    }

    public function saveEntry(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $schoolId = school_id();
        $competitionId = (int)($_POST['competition_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $role = (string)($_POST['participation_role'] ?? 'solo');
        $status = (string)($_POST['status'] ?? 'registered');
        $entryName = trim((string)($_POST['entry_name'] ?? ''));
        if (!in_array($role, self::ENTRY_ROLES, true) || !in_array($status, self::ENTRY_STATUSES, true)
            || ($studentId === 0 && $entryName === '')) {
            $this->respond(false, 'Choose a student or enter an entry name, plus a valid role and status.', 'cocurricular', ['tab' => 'competitions']);
        }
        if ($studentId > 0 && !CocurricularModule::studentExists($studentId, $schoolId)) {
            $this->respond(false, 'Choose a student from this school.', 'cocurricular', ['tab' => 'competitions']);
        }
        $clubId = (int)($_POST['club_id'] ?? 0);
        if ($clubId > 0 && !CocurricularModule::club($clubId, $schoolId)) {
            $this->respond(false, 'Choose a club from this school.', 'cocurricular', ['tab' => 'competitions']);
        }
        $teamId = (int)($_POST['team_id'] ?? 0);
        if ($teamId > 0 && !CocurricularModule::team($teamId, $schoolId)) {
            $this->respond(false, 'Choose a team from this school.', 'cocurricular', ['tab' => 'competitions']);
        }
        $fee = trim((string)($_POST['fee_amount'] ?? ''));
        if ($fee !== '' && (!is_numeric($fee) || (float)$fee < 0)) {
            $this->respond(false, 'Enter a valid entry fee or leave it blank.', 'cocurricular', ['tab' => 'competitions']);
        }
        $score = trim((string)($_POST['score'] ?? ''));
        if ($score !== '' && !is_numeric($score)) {
            $this->respond(false, 'Enter the score as a number or leave it blank.', 'cocurricular', ['tab' => 'competitions']);
        }
        $data = [
            'competition_id' => $competitionId,
            'student_id' => $studentId > 0 ? $studentId : null,
            'entry_name' => $entryName !== '' ? mb_substr($entryName, 0, 180) : null,
            'category' => $this->line($_POST['category'] ?? '', 120),
            'participation_role' => $role,
            'club_id' => $clubId > 0 ? $clubId : null,
            'team_id' => $teamId > 0 ? $teamId : null,
            'registration_date' => $this->dateOrNull($_POST['registration_date'] ?? '') ?? date('Y-m-d'),
            'fee_amount' => $fee !== '' ? round((float)$fee, 2) : null,
            'score' => $score !== '' ? round((float)$score, 2) : null,
            'position' => $this->line($_POST['position'] ?? '', 40),
            'award' => $this->line($_POST['award'] ?? '', 120),
            'remarks' => $this->line($_POST['remarks'] ?? '', 500),
            'status' => $status,
        ];
        try {
            $entryId = CocurricularModule::saveEntry($schoolId, $data);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'competitions']);
        }
        audit('cocurricular', 'create', 'competition_entries', $entryId, 'Registered competition entry #' . $entryId);
        $this->respond(true, 'Competition entry saved.', 'cocurricular', ['tab' => 'competitions']);
    }

    // ------------------------------------------------------------------
    // Talent records
    // ------------------------------------------------------------------

    public function saveTalent(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $schoolId = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $type = (string)($_POST['talent_type'] ?? 'other');
        $name = trim((string)($_POST['talent_name'] ?? ''));
        $level = (string)($_POST['level'] ?? 'school');
        if (!CocurricularModule::studentExists($studentId, $schoolId) || $name === '' || mb_strlen($name) > 160
            || !in_array($type, self::TALENT_TYPES, true) || !in_array($level, self::LEVELS, true)) {
            $this->respond(false, 'Choose a student, talent type, level and enter the talent name.', 'cocurricular', ['tab' => 'talent']);
        }
        $data = [
            'student_id' => $studentId,
            'talent_type' => $type,
            'talent_name' => $name,
            'description' => $this->line($_POST['description'] ?? '', 500),
            'level' => $level,
            'achievement' => $this->line($_POST['achievement'] ?? '', 255),
            'award_title' => $this->line($_POST['award_title'] ?? '', 160),
            'award_date' => $this->dateOrNull($_POST['award_date'] ?? ''),
            'leadership_role' => $this->line($_POST['leadership_role'] ?? '', 120),
            'date_recorded' => $this->dateOrNull($_POST['date_recorded'] ?? '') ?? date('Y-m-d'),
            'verified_by' => (int)(Auth::user()['staff_id'] ?? 0) ?: null,
            'is_testimonial_note' => isset($_POST['is_testimonial_note']) ? 1 : 0,
            'notes' => $this->line($_POST['notes'] ?? '', 500),
        ];
        $id = CocurricularModule::saveTalent($schoolId, $data);
        audit('cocurricular', 'create', 'talent_records', $id, 'Recorded talent for student #' . $studentId);
        $this->respond(true, 'Talent record saved.', 'cocurricular', ['tab' => 'talent']);
    }

    // ------------------------------------------------------------------
    // House competition points
    // ------------------------------------------------------------------

    public function saveHousePoints(): void
    {
        if (!$this->requirePermission('add')) {
            return;
        }
        $schoolId = school_id();
        $category = (string)($_POST['category'] ?? 'sports');
        $name = trim((string)($_POST['event_name'] ?? ''));
        $date = $this->dateOrNull($_POST['event_date'] ?? '');
        $points = is_numeric($_POST['points'] ?? null) ? round((float)$_POST['points'], 2) : -1;
        $pointsType = (string)($_POST['points_type'] ?? 'award');
        if ($name === '' || mb_strlen($name) > 160 || $date === null || $points <= 0
            || !in_array($category, self::HOUSE_CATEGORIES, true)
            || !in_array($pointsType, ['award', 'penalty'], true)) {
            $this->respond(false, 'Choose a house, category and date, then enter the event and positive points.', 'cocurricular', ['tab' => 'houses']);
        }
        $data = [
            'house_id' => (int)($_POST['house_id'] ?? 0),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'term_id' => (int)($_POST['term_id'] ?? 0) ?: null,
            'category' => $category,
            'event_name' => $name,
            'points' => $points,
            'points_type' => $pointsType,
            'event_date' => $date,
            'description' => $this->line($_POST['description'] ?? '', 255),
            'awarded_by' => (int)(Auth::user()['staff_id'] ?? 0) ?: null,
        ];
        try {
            $id = CocurricularModule::saveHousePoints($schoolId, $data);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'cocurricular', ['tab' => 'houses']);
        }
        audit('cocurricular', 'create', 'house_competition_points', $id, 'Recorded ' . $pointsType . ' of ' . $points . ' points for house #' . $data['house_id']);
        $this->respond(true, 'House points recorded.', 'cocurricular', ['tab' => 'houses']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function requirePermission(string $action): bool
    {
        if (Auth::can('cocurricular', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission for this co-curricular action.', 'cocurricular');
        return false;
    }

    private function line(mixed $value, int $max): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        return $date !== false && $date->format('Y-m-d') === $text ? $text : null;
    }

    private function timeOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $time = DateTimeImmutable::createFromFormat('!H:i', $text);
        return $time !== false && $time->format('H:i') === $text ? $text . ':00' : null;
    }
}
