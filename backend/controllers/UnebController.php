<?php
declare(strict_types=1);

final class UnebController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('uneb', 'view')) {
            return;
        }
        $schoolId = school_id();
        $levels = UnebModule::levels($schoolId);
        $stats = UnebModule::stats($schoolId);
        $levelId = (int)($_GET['level_id'] ?? 0);
        $candidates = UnebModule::candidates($schoolId, $levelId > 0 ? $levelId : null);

        $this->render('uneb/index', [
            'pageTitle' => 'UNEB Management',
            'activeNav' => 'uneb',
            'levels' => $levels,
            'stats' => $stats,
            'candidates' => $candidates,
            'selectedLevel' => $levelId,
            'canAdd' => Auth::can('uneb', 'add'),
            'canEdit' => Auth::can('uneb', 'edit'),
        ]);
    }

    public function register(): void
    {
        if (!$this->allowed('uneb', 'add')) {
            return;
        }
        $schoolId = school_id();
        $levels = UnebModule::levels($schoolId);
        $students = Student::listForSchool($schoolId, ['status' => 'active']);
        $subjects = AcademicManagement::subjects($schoolId);

        $this->render('uneb/register', [
            'pageTitle' => 'Register UNEB Candidates',
            'activeNav' => 'uneb',
            'levels' => $levels,
            'students' => $students,
            'subjects' => $subjects,
        ]);
    }

    public function saveCandidate(): void
    {
        if (!$this->allowed('uneb', 'add')) {
            return;
        }
        $schoolId = school_id();
        $actorId = (int)Auth::user()['id'];

        $data = [
            'index_number' => trim((string)($_POST['index_number'] ?? '')),
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'gender' => (string)($_POST['gender'] ?? ''),
            'date_of_birth' => (string)($_POST['date_of_birth'] ?? '') ?: null,
            'student_id' => (int)($_POST['student_id'] ?? 0) ?: null,
            'level_id' => (int)($_POST['level_id'] ?? 0),
            'status' => (string)($_POST['status'] ?? 'registered'),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        if ($data['index_number'] === '' || $data['full_name'] === '' || $data['level_id'] <= 0) {
            $this->respond(false, 'Index number, full name and level are required.', 'uneb/register');
        }
        if (!in_array($data['gender'], ['male', 'female'], true)) {
            $this->respond(false, 'Choose a valid gender.', 'uneb/register');
        }

        $id = UnebModule::saveCandidate($schoolId, $data, null, $actorId);
        $subjectIds = array_map('intval', (array)($_POST['subject_ids'] ?? []));
        UnebModule::saveCandidateSubjects($id, $subjectIds);

        audit('uneb', 'create', 'uneb_candidates', $id, 'Registered candidate ' . $data['index_number']);
        $this->respond(true, 'Candidate registered.', 'uneb');
    }

    public function editCandidate(): void
    {
        if (!$this->allowed('uneb', 'edit')) {
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        $schoolId = school_id();
        $candidate = UnebModule::candidate($id, $schoolId);
        if (!$candidate) {
            flash_set('warning', 'Candidate not found.');
            redirect('uneb');
        }
        $subjects = AcademicManagement::subjects($schoolId);
        $selectedSubjects = array_column(UnebModule::candidateSubjects($id), 'subject_id');
        $levels = UnebModule::levels($schoolId);

        $this->render('uneb/edit', [
            'pageTitle' => 'Edit Candidate',
            'activeNav' => 'uneb',
            'candidate' => $candidate,
            'subjects' => $subjects,
            'selectedSubjects' => $selectedSubjects,
            'levels' => $levels,
        ]);
    }

    public function updateCandidate(): void
    {
        if (!$this->allowed('uneb', 'edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        $actorId = (int)Auth::user()['id'];

        $candidate = UnebModule::candidate($id, $schoolId);
        if (!$candidate) {
            $this->respond(false, 'Candidate not found.', 'uneb');
        }

        $data = [
            'index_number' => trim((string)($_POST['index_number'] ?? '')),
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'gender' => (string)($_POST['gender'] ?? ''),
            'date_of_birth' => (string)($_POST['date_of_birth'] ?? '') ?: null,
            'student_id' => (int)($_POST['student_id'] ?? 0) ?: null,
            'level_id' => (int)($_POST['level_id'] ?? 0),
            'status' => (string)($_POST['status'] ?? 'registered'),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        UnebModule::saveCandidate($schoolId, $data, $id, $actorId);
        $subjectIds = array_map('intval', (array)($_POST['subject_ids'] ?? []));
        UnebModule::saveCandidateSubjects($id, $subjectIds);

        audit('uneb', 'update', 'uneb_candidates', $id, 'Updated candidate ' . $data['index_number']);
        $this->respond(true, 'Candidate updated.', 'uneb');
    }

    public function deleteCandidate(): void
    {
        if (!$this->allowed('uneb', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        if (UnebModule::deleteCandidate($id, $schoolId)) {
            audit('uneb', 'delete', 'uneb_candidates', $id, 'Deleted UNEB candidate');
            $this->respond(true, 'Candidate deleted.', 'uneb');
        }
        $this->respond(false, 'Could not delete candidate.', 'uneb');
    }

    // ------------------------------------------------- Continuous Assessment

    public function casubmission(): void
    {
        if (!$this->allowed('uneb', 'view')) {
            return;
        }
        $schoolId = school_id();
        $levels = UnebModule::levels($schoolId);
        $levelId = (int)($_GET['level_id'] ?? 0);
        $submissions = UnebModule::casubmissions($schoolId, $levelId > 0 ? $levelId : null);

        $this->render('uneb/ca_submission', [
            'pageTitle' => 'UNEB Continuous Assessment Submission',
            'activeNav' => 'uneb',
            'levels' => $levels,
            'submissions' => $submissions,
            'selectedLevel' => $levelId,
            'canAdd' => Auth::can('uneb', 'add'),
        ]);
    }

    public function newCasubmission(): void
    {
        if (!$this->allowed('uneb', 'add')) {
            return;
        }
        $schoolId = school_id();
        $levels = UnebModule::levels($schoolId);
        $years = AcademicManagement::years($schoolId);

        $this->render('uneb/ca_new', [
            'pageTitle' => 'New CA Submission',
            'activeNav' => 'uneb',
            'levels' => $levels,
            'years' => $years,
        ]);
    }

    public function saveCasubmission(): void
    {
        if (!$this->allowed('uneb', 'add')) {
            return;
        }
        $schoolId = school_id();
        $actorId = (int)Auth::user()['id'];

        $data = [
            'level_id' => (int)($_POST['level_id'] ?? 0),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'term_id' => (int)($_POST['term_id'] ?? 0) ?: null,
            'submission_type' => (string)($_POST['submission_type'] ?? 'internal'),
            'status' => (string)($_POST['status'] ?? 'draft'),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        if ($data['level_id'] <= 0 || $data['academic_year_id'] <= 0) {
            $this->respond(false, 'Level and academic year are required.', 'uneb/ca-submission');
        }

        $id = UnebModule::saveCasubmission($schoolId, $data, $actorId);
        audit('uneb', 'create', 'uneb_ca_submission', $id, 'Created CA submission for level ' . $data['level_id']);
        $this->respond(true, 'CA submission created. Add candidate scores.', 'uneb/ca-scores', ['id' => $id]);
    }

    public function caScores(): void
    {
        if (!$this->allowed('uneb', 'view')) {
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        $schoolId = school_id();
        $submission = UnebModule::casubmission($id, $schoolId);
        if (!$submission) {
            flash_set('warning', 'CA submission not found.');
            redirect('uneb/ca-submission');
        }
        $scores = UnebModule::casubmissionScores($id);
        if (!$scores && ($submission['status'] ?? '') !== 'submitted') {
            UnebModule::seedCaScoreRows($id, $schoolId, (int)$submission['level_id']);
            $scores = UnebModule::casubmissionScores($id);
        }
        $levels = UnebModule::levels($schoolId);
        $years = AcademicManagement::years($schoolId);
        $terms = $submission['academic_year_id'] ? AcademicManagement::terms($schoolId, (int)$submission['academic_year_id']) : [];

        $this->render('uneb/ca_scores', [
            'pageTitle' => 'CA Candidate Scores',
            'activeNav' => 'uneb',
            'submission' => $submission,
            'scores' => $scores,
            'levels' => $levels,
            'years' => $years,
            'terms' => $terms,
            'canEdit' => Auth::can('uneb', 'edit'),
        ]);
    }

    public function saveCaScores(): void
    {
        if (!$this->allowed('uneb', 'edit')) {
            return;
        }
        $id = (int)($_POST['submission_id'] ?? 0);
        $schoolId = school_id();
        $submission = UnebModule::casubmission($id, $schoolId);
        if (!$submission) {
            $this->respond(false, 'Submission not found.', 'uneb/ca-submission');
        }

        $scores = [];
        foreach ((array)($_POST['ca_score'] ?? []) as $candidateId => $subjectRows) {
            foreach ((array)$subjectRows as $subjectId => $row) {
                $scores[(int)$candidateId][(int)$subjectId] = [
                    'ca' => ($row['ca'] ?? '') === '' ? null : (float)$row['ca'],
                    'project' => ($row['project'] ?? '') === '' ? null : (float)$row['project'],
                    'overall' => ($row['overall'] ?? '') === '' ? null : (float)$row['overall'],
                ];
            }
        }

        UnebModule::saveCaScores($id, $scores);
        audit('uneb', 'update', 'uneb_ca_subject_scores', $id, 'Saved CA scores for submission ' . $id);
        $this->respond(true, 'CA scores saved.', 'uneb/ca-scores', ['id' => $id]);
    }

    public function submitCasubmission(): void
    {
        if (!$this->allowed('uneb', 'approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        $submitted = false;
        $endpoint = (string)Setting::get($schoolId, 'uneb.external_api_endpoint', '');
        if ($endpoint !== '') {
            $submission = UnebModule::casubmission($id, $schoolId);
            if ($submission) {
                $result = self::postToExternal($endpoint, [
                    'school_id' => $schoolId,
                    'submission_id' => $id,
                    'level_id' => $submission['level_id'] ?? 0,
                    'status' => $submission['status'] ?? '',
                    'submitted_at' => date('Y-m-d H:i:s'),
                ]);
                if ($result['ok']) {
                    $submitted = UnebModule::markCasubmitted($id, $schoolId);
                    audit('uneb', 'approve', 'uneb_ca_submission', $id, 'CA scores submitted to UNEB external API');
                } else {
                    audit('uneb', 'approve', 'uneb_ca_submission', $id, 'UNEB external API failed: ' . $result['error']);
                    $this->respond(false, 'External UNEB submission failed: ' . $result['error'], 'uneb/ca-submission');
                    return;
                }
            }
        }

        if (!$submitted) {
            $submitted = UnebModule::markCasubmitted($id, $schoolId);
        }

        if ($submitted) {
            audit('uneb', 'approve', 'uneb_ca_submission', $id, 'CA scores marked as submitted to UNEB.');
            $this->respond(true, 'CA scores submitted to UNEB.', 'uneb/ca-submission');
        }
        $this->respond(false, 'Could not submit CA scores.', 'uneb/ca-submission');
    }

    private static function postToExternal(string $url, array $payload): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'error' => $err, 'http_code' => $httpCode];
        }
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'response' => $response, 'http_code' => $httpCode];
    }

    // ------------------------------------------------- National Results

    public function results(): void
    {
        if (!$this->allowed('uneb', 'view')) {
            return;
        }
        $schoolId = school_id();
        $levels = UnebModule::levels($schoolId);
        $levelId = (int)($_GET['level_id'] ?? 0);
        $year = (int)($_GET['year'] ?? 0);
        $results = UnebModule::results($schoolId, $levelId > 0 ? $levelId : null, $year > 0 ? $year : 0);

        $this->render('uneb/results', [
            'pageTitle' => 'UNEB National Results',
            'activeNav' => 'uneb',
            'levels' => $levels,
            'results' => $results,
            'selectedLevel' => $levelId,
            'selectedYear' => $year,
        ]);
    }

    public function saveResult(): void
    {
        if (!$this->allowed('uneb', 'add')) {
            return;
        }
        $schoolId = school_id();
        $actorId = (int)Auth::user()['id'];

        $data = [
            'level_id' => (int)($_POST['level_id'] ?? 0),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'exam_year' => (int)($_POST['exam_year'] ?? 0),
            'index_number' => trim((string)($_POST['index_number'] ?? '')),
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'gender' => (string)($_POST['gender'] ?? ''),
            'subject_results' => trim((string)($_POST['subject_results'] ?? '')) ?: null,
            'overall_level' => trim((string)($_POST['overall_level'] ?? '')) ?: null,
            'result_code' => trim((string)($_POST['result_code'] ?? '')) ?: null,
            'ca_score_submitted' => isset($_POST['ca_score_submitted']),
            'notes' => trim((string)($_POST['notes'] ?? '')) ?: null,
        ];

        if ($data['level_id'] <= 0 || $data['index_number'] === '' || $data['full_name'] === '') {
            $this->respond(false, 'Level, index number and full name are required.', 'uneb/results');
        }

        UnebModule::saveResult($schoolId, $data, $actorId);
        audit('uneb', 'create', 'uneb_results', null, 'Recorded UNEB result ' . $data['index_number']);
        $this->respond(true, 'UNEB result recorded.', 'uneb/results');
    }
}
