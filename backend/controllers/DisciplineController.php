<?php
declare(strict_types=1);

final class DisciplineController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $status   = trim((string)($_GET['status'] ?? ''));
        $editId   = (int)($_GET['edit'] ?? 0);
        $edit     = $editId > 0 ? DisciplineIncident::find($editId) : null;

        $this->render('students/discipline', [
            'pageTitle' => 'Student discipline',
            'activeNav' => 'students/discipline',
            'incidents' => DisciplineIncident::listForSchool($schoolId, $status),
            'status'    => $status,
            'edit'      => $edit,
            'students'  => Student::listForSchool($schoolId, ['status' => 'active']),
            'staff'     => Staff::forSchool($schoolId),
            'canAdd'    => Auth::can('students', 'add'),
            'canEdit'   => Auth::can('students', 'edit'),
        ]);
    }

    public function store(): void
    {
        $schoolId = school_id();
        $errors   = [];

        $studentId = (int)($_POST['student_id'] ?? 0);
        $student   = $studentId > 0 ? Student::find($studentId) : null;
        if (!$student || (int)$student['school_id'] !== $schoolId) {
            $errors['student_id'] = 'Choose a valid student.';
        }

        $date = (string)($_POST['incident_date'] ?? '');
        if ($date === '' || !strtotime($date)) {
            $errors['incident_date'] = 'Choose a valid incident date.';
        }

        $category = (string)($_POST['category'] ?? '');
        $categories = ['late_coming', 'truancy', 'disrespect', 'fighting', 'theft', 'vandalism', 'cheating', 'substance', 'uniform', 'bullying', 'other'];
        if (!in_array($category, $categories, true)) {
            $errors['category'] = 'Choose an incident category.';
        }

        $description = trim((string)($_POST['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = 'Describe what happened.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'students/discipline', [], $errors);
        }

        $sanction = (string)($_POST['sanction'] ?? 'warning');
        if (!in_array($sanction, ['none', 'warning', 'counselling', 'community_service', 'suspension', 'expulsion', 'parent_meeting'], true)) {
            $sanction = 'warning';
        }

        $witness = (int)($_POST['witness_staff_id'] ?? 0);
        $reported = (int)($_POST['reported_by'] ?? 0);

        $id = DisciplineIncident::insert([
            'student_id'       => $studentId,
            'incident_date'    => $date,
            'incident_time'    => trim((string)($_POST['incident_time'] ?? '')) ?: null,
            'category'         => $category,
            'location'         => trim((string)($_POST['location'] ?? '')) ?: null,
            'description'      => $description,
            'witness_staff_id' => $witness > 0 ? $witness : null,
            'reported_by'      => $reported > 0 ? $reported : null,
            'action_taken'     => trim((string)($_POST['action_taken'] ?? '')) ?: null,
            'sanction'         => $sanction,
            'sanction_start'   => trim((string)($_POST['sanction_start'] ?? '')) ?: null,
            'sanction_end'     => trim((string)($_POST['sanction_end'] ?? '')) ?: null,
            'parent_notified'  => isset($_POST['parent_notified']) ? 1 : 0,
            'parent_notified_at' => isset($_POST['parent_notified']) ? date('Y-m-d H:i:s') : null,
            'status'           => 'open',
            'follow_up_date'   => trim((string)($_POST['follow_up_date'] ?? '')) ?: null,
        ]);

        audit('students', 'create', 'discipline_incidents', $id,
            'Recorded discipline incident for ' . $student['admission_number'], null, ['category' => $category, 'sanction' => $sanction]);

        $this->respond(true, 'Discipline incident recorded.', 'students/discipline');
    }

    public function update(): void
    {
        $id  = (int)($_POST['id'] ?? 0);
        $row = DisciplineIncident::find($id);
        if (!$row) {
            $this->respond(false, 'That incident no longer exists.', 'students/discipline');
        }

        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['open', 'under_review', 'follow_up', 'closed'], true)) {
            $this->respond(false, 'Choose a valid status.', 'students/discipline');
        }

        $fields = [
            'status'         => $status,
            'action_taken'   => trim((string)($_POST['action_taken'] ?? '')) ?: $row['action_taken'],
            'follow_up_date' => trim((string)($_POST['follow_up_date'] ?? '')) ?: null,
            'follow_up_notes'=> trim((string)($_POST['follow_up_notes'] ?? '')) ?: null,
        ];

        if ($status === 'closed') {
            $fields['closed_by'] = (int)Auth::user()['id'];
            $fields['closed_at'] = date('Y-m-d H:i:s');
        }

        DisciplineIncident::updateRow($id, $fields);
        audit('students', 'update', 'discipline_incidents', $id, 'Updated incident #' . $id . ' to ' . $status);

        $this->respond(true, 'Incident updated.', 'students/discipline');
    }
}
