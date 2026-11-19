<?php
declare(strict_types=1);

/**
 * Student transfers and transfer certificates.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class TransferController extends Controller
{
    public function index(): void
    {
        $schoolId  = school_id();
        $direction = trim((string)($_GET['direction'] ?? ''));

        $this->render('students/transfers', [
            'pageTitle' => 'Student transfers',
            'activeNav' => 'students/transfers',
            'transfers' => Transfer::listForSchool($schoolId, $direction),
            'direction' => $direction,
            'classes'   => SchoolClass::activeForSchool($schoolId),
            'students'  => Student::listForSchool($schoolId, ['status' => 'active']),
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

        $direction = (string)($_POST['direction'] ?? '');
        if (!in_array($direction, ['in', 'out'], true)) {
            $errors['direction'] = 'Choose transfer in or transfer out.';
        }

        $otherSchool = trim((string)($_POST['other_school_name'] ?? ''));
        if ($otherSchool === '') {
            $errors['other_school_name'] = 'Enter the other school name.';
        }

        $date = (string)($_POST['transfer_date'] ?? '');
        if ($date === '' || !strtotime($date)) {
            $errors['transfer_date'] = 'Choose a valid transfer date.';
        }

        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'students/transfers', [], $errors);
        }

        $receivingClassId = (int)($_POST['receiving_class_id'] ?? 0);
        if ($direction === 'in' && $receivingClassId > 0) {
            $class = SchoolClass::find($receivingClassId);
            if (!$class || (int)$class['school_id'] !== $schoolId) {
                $errors['receiving_class_id'] = 'Choose a valid class.';
                $this->respond(false, 'Please correct the highlighted fields.', 'students/transfers', [], $errors);
            }
        }

        $userId = (int)Auth::user()['id'];
        $year   = AcademicYear::active($schoolId);
        $term   = Term::active();
        $db     = Database::conn();
        $db->beginTransaction();

        try {
            $transferId = Transfer::insert([
                'student_id'         => $studentId,
                'direction'          => $direction,
                'other_school_name'  => $otherSchool,
                'reason'             => trim((string)($_POST['reason'] ?? '')) ?: null,
                'transfer_date'      => $date,
                'previous_class'     => trim((string)($_POST['previous_class'] ?? '')) ?: null,
                'previous_result'    => trim((string)($_POST['previous_result'] ?? '')) ?: null,
                'receiving_class_id' => $direction === 'in' && $receivingClassId > 0 ? $receivingClassId : null,
                'documents_received' => isset($_POST['documents_received']) ? 1 : 0,
                'approved_by'        => $userId,
                'approved_at'        => date('Y-m-d H:i:s'),
                'notes'              => trim((string)($_POST['notes'] ?? '')) ?: null,
            ]);

            if ($direction === 'out') {
                Student::updateRow($studentId, [
                    'status'        => 'transferred_out',
                    'status_reason' => 'Transferred to ' . $otherSchool,
                    'leaving_date'  => $date,
                ]);
            } elseif ($receivingClassId > 0) {
                Student::assignClass($studentId, $receivingClassId, null);
                if ($year) {
                    Student::recordPlacement(
                        $studentId,
                        $receivingClassId,
                        null,
                        (int)$year['id'],
                        $term ? (int)$term['id'] : null,
                        'transfer_in',
                        $date,
                        'Joined from ' . $otherSchool,
                        $userId
                    );
                }
            }

            $db->commit();
        } catch (Throwable $error) {
            $db->rollBack();
            throw $error;
        }

        audit('students', 'transfer', 'transfers', $transferId,
            ($direction === 'in' ? 'Transfer in' : 'Transfer out') . ' recorded for ' . $student['admission_number']);

        $this->respond(true, 'Transfer recorded for ' . $student['first_name'] . ' ' . $student['last_name'] . '.', 'students/transfers');
    }

    public function transferCertificate(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $db = Database::conn();
        $student = $db->prepare(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name, h.name AS house_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
               LEFT JOIN houses h ON h.id = s.house_id
              WHERE s.id = ? AND s.school_id = ?'
        );
        $student->execute([$studentId, $schoolId]);
        $student = $student->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'students/transfers');
            return;
        }
        $school = school_row() ?? [];
        $html = PdfService::transferCertificateHtml($student, $school, [
            'reason' => trim((string)($_GET['reason'] ?? 'Transfer to another institution')),
            'conduct' => trim((string)($_GET['conduct'] ?? 'Satisfactory')),
            'academic_standing' => trim((string)($_GET['academic_standing'] ?? 'Good')),
        ]);
        PdfService::output($html, 'transfer_certificate_'.$student['admission_number'].'.pdf');
    }
}
