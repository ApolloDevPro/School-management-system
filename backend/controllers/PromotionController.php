<?php
declare(strict_types=1);

/**
 * End-of-year student promotion.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class PromotionController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $batchId  = (int)($_GET['batch_id'] ?? 0);
        $batch    = $batchId > 0 ? Promotion::find($batchId) : null;
        if ($batch && (int)$batch['school_id'] !== $schoolId) {
            $batch = null;
        }

        $classes = SchoolClass::activeForSchool($schoolId);
        $byName = [];
        foreach ($classes as $class) {
            $byName[$class['name']] = $class;
        }

        $this->render('students/promotions', [
            'pageTitle' => 'Student promotion',
            'activeNav' => 'students/promotions',
            'batches'   => Promotion::batchesForSchool($schoolId),
            'batch'     => $batch,
            'records'   => $batch ? Promotion::recordsOf((int)$batch['id']) : [],
            'classes'   => $classes,
            'byName'    => $byName,
            'years'     => AcademicYear::forSchool($schoolId),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function store(): void
    {
        $schoolId = school_id();
        $name     = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $this->respond(false, 'Enter a batch name.', 'students/promotions', [], ['name' => 'Required.']);
        }

        $yearId = (int)($_POST['academic_year_id'] ?? 0);
        $year   = $yearId > 0 ? AcademicYear::find($yearId) : null;
        if (!$year || (int)$year['school_id'] !== $schoolId) {
            $this->respond(false, 'Choose a valid academic year.', 'students/promotions', [], ['academic_year_id' => 'Required.']);
        }

        $fromLevel = (string)($_POST['from_level'] ?? '');
        if (!in_array($fromLevel, ['S1', 'S2', 'S3', 'S4', 'S5'], true)) {
            $this->respond(false, 'Choose the level being promoted.', 'students/promotions', [], ['from_level' => 'Required.']);
        }

        $id = Promotion::insert([
            'school_id'        => $schoolId,
            'academic_year_id' => (int)$year['id'],
            'name'             => $name,
            'from_level'       => $fromLevel,
            'promotion_rules'  => trim((string)($_POST['promotion_rules'] ?? '')) ?: null,
            'status'           => 'draft',
        ]);

        audit('students', 'create', 'promotion_batches', $id, 'Created promotion batch ' . $name);

        $this->respond(true, 'Promotion batch created. Now process it.', 'students/promotions', ['batch_id' => $id]);
    }

    public function process(): void
    {
        $schoolId = school_id();
        $batchId  = (int)($_POST['batch_id'] ?? 0);
        $batch    = Promotion::find($batchId);
        if (!$batch || (int)$batch['school_id'] !== $schoolId) {
            $this->respond(false, 'That promotion batch no longer exists.', 'students/promotions');
        }
        if ($batch['status'] === 'completed') {
            $this->respond(false, 'This batch has already been processed.', 'students/promotions', ['batch_id' => $batchId]);
        }

        $classes = SchoolClass::activeForSchool($schoolId);
        $order = [];
        foreach ($classes as $class) {
            $order[$class['name']] = $class;
        }

        $fromName = $batch['from_level'];
        if (!isset($order[$fromName])) {
            $this->respond(false, 'The class ' . $fromName . ' no longer exists.', 'students/promotions', ['batch_id' => $batchId]);
        }

        $nextName = ['S1' => 'S2', 'S2' => 'S3', 'S3' => 'S4', 'S4' => 'S5', 'S5' => 'S6'][$fromName] ?? null;
        $toClass  = $nextName !== null && isset($order[$nextName]) ? $order[$nextName] : null;
        $fromClass = $order[$fromName];

        $students = Student::byClass((int)$fromClass['id']);
        if (!$students) {
            $this->respond(false, 'No active students found in ' . $fromName . '.', 'students/promotions', ['batch_id' => $batchId]);
        }

        $userId = (int)Auth::user()['id'];
        $term   = Term::active();
        $db     = Database::conn();
        $db->beginTransaction();

        try {
            $processed = 0;
            foreach ($students as $student) {
                $completed = in_array($fromName, ['S4', 'S6'], true);
                $decision  = $completed ? 'completed' : 'promoted';
                $toId      = $completed ? null : ($toClass ? (int)$toClass['id'] : null);
                $streamId  = (int)($student['current_stream_id'] ?? 0) ?: null;

                Promotion::insert([
                    'batch_id'         => $batchId,
                    'student_id'       => (int)$student['id'],
                    'academic_year_id' => (int)$batch['academic_year_id'],
                    'from_class_id'    => (int)$fromClass['id'],
                    'to_class_id'      => $toId,
                    'from_stream_id'   => $streamId,
                    'to_stream_id'     => $streamId,
                    'decision'         => $decision,
                    'reason'           => $completed ? 'Completed ' . $fromName : 'Promoted to ' . $nextName,
                    'criteria_used'    => $batch['promotion_rules'] ?: 'End of year progression',
                    'decided_by'       => $userId,
                    'decided_date'     => date('Y-m-d'),
                ]);

                if ($completed) {
                    Student::updateRow((int)$student['id'], ['status' => 'completed']);
                    Student::recordPlacement(
                        (int)$student['id'],
                        (int)$fromClass['id'],
                        $streamId,
                        (int)$batch['academic_year_id'],
                        $term ? (int)$term['id'] : null,
                        'completion',
                        date('Y-m-d'),
                        'Completed ' . $fromName,
                        $userId
                    );
                } elseif ($toId !== null) {
                    Student::assignClass((int)$student['id'], $toId, $streamId);
                    Student::recordPlacement(
                        (int)$student['id'],
                        $toId,
                        $streamId,
                        (int)$batch['academic_year_id'],
                        $term ? (int)$term['id'] : null,
                        'promotion',
                        date('Y-m-d'),
                        'Promoted from ' . $fromName,
                        $userId
                    );
                }

                $processed++;
            }

            Promotion::updateRow($batchId, [
                'status'        => 'completed',
                'total_processed' => $processed,
                'processed_by'  => $userId,
                'processed_at'  => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'process_promotion', 'promotion_batches', $batchId,
            'Processed ' . $processed . ' student(s) in batch ' . $batch['name']);

        $this->respond(true, $processed . ' student(s) processed. ' . ($toClass ? 'Moved to ' . $toClass['name'] . '.' : 'Marked as completed.'), 'students/promotions', ['batch_id' => $batchId]);
    }
}
