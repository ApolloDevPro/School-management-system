<?php
declare(strict_types=1);

/**
 * Dormitories, bed allocation, boarding status and allocation letters.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class BoardingController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('boarding/index', [
            'pageTitle'     => 'Boarding',
            'activeNav'     => 'boarding',
            'registrations' => BoardingModule::registrations($schoolId),
            'students'      => BoardingModule::students($schoolId),
            'years'         => BoardingModule::years($schoolId),
            'terms'         => BoardingModule::terms($schoolId),
            'dormitories'   => BoardingModule::dormitories($schoolId),
            'canAdd'        => Auth::can('boarding', 'add'),
            'canEdit'       => Auth::can('boarding', 'edit'),
        ]);
    }

    public function register(): void
    {
        $schoolId  = school_id();
        $studentId = (int)($_POST['student_id'] ?? 0);
        $yearId    = (int)($_POST['academic_year_id'] ?? 0);
        $termId    = (int)($_POST['term_id'] ?? 0);
        $dormId    = (int)($_POST['dormitory_id'] ?? 0);

        $errors = [];
        if (!BoardingModule::validStudent($studentId, $schoolId)) { $errors['student_id'] = 'Choose a valid active student.'; }
        if (!BoardingModule::validYearTerm($yearId, $termId, $schoolId)) { $errors['term_id'] = 'Choose a valid year and term.'; }
        if ($errors) { $this->respond(false, 'Please correct the highlighted fields.', 'boarding', [], $errors); }

        $id = BoardingModule::createRegistration([
            'student_id'        => $studentId,
            'year_id'           => $yearId,
            'term_id'           => $termId,
            'dormitory_id'      => $dormId > 0 ? $dormId : null,
            'emergency_contact' => trim((string)($_POST['emergency_contact'] ?? '')) ?: null,
            'notes'             => trim((string)($_POST['notes'] ?? '')) ?: null,
            'user_id'           => (int)Auth::user()['id'],
        ]);

        audit('boarding', 'registered', 'boarding_registrations', $id, 'Boarding registration created');
        $this->respond(true, 'Student registered for boarding.', 'boarding');
    }

    public function updateStatus(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['registered', 'checked_in', 'checked_out', 'cancelled'], true)) {
            $this->respond(false, 'Invalid status.', 'boarding');
        }
        $ok = BoardingModule::changeStatus($id, school_id(), $status);
        $this->respond($ok, $ok ? 'Boarding status updated.' : 'Could not update status.', 'boarding');
    }

    public function boardingAllocationLetter(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $db = Database::conn();
        $student = Database::query(
            'SELECT s.*, c.name AS class_name, st.name AS stream_name
               FROM students s
               LEFT JOIN classes c ON c.id = s.current_class_id
               LEFT JOIN streams st ON st.id = s.current_stream_id
              WHERE s.id = ? AND s.school_id = ?',
            [$studentId, $schoolId]
        )->fetch();
        if (!$student) {
            $this->respond(false, 'Student not found.', 'boarding');
            return;
        }
        $registration = Database::query(
            'SELECT br.*, d.name AS dormitory_name, w.first_name AS warden_first, w.last_name AS warden_last,
                    y.name AS year_name, t.name AS term_name
               FROM boarding_registrations br
               LEFT JOIN dormitories d ON d.id = br.dormitory_id
               LEFT JOIN staff w ON w.id = d.warden_staff_id
               LEFT JOIN academic_years y ON y.id = br.academic_year_id
               LEFT JOIN terms t ON t.id = br.term_id
              WHERE br.student_id = ?
              ORDER BY br.id DESC LIMIT 1',
            [$studentId]
        )->fetch();
        $school = school_row() ?? [];
        $wardenName = $registration ? ($registration['warden_first'].' '.$registration['warden_last']) : '-';
        $html = PdfService::boardingAllocationLetterHtml($student, $school, [
            'dormitory_name' => $registration['dormitory_name'] ?? '-',
            'bed_number' => $registration['bed_number'] ?? '-',
            'warden_name' => trim($wardenName),
            'academic_year' => $registration['year_name'] ?? '-',
            'term' => $registration['term_name'] ?? '-',
            'reporting_date' => date('d M Y'),
        ]);
        PdfService::output($html, 'boarding_allocation_'.$student['admission_number'].'.pdf');
    }
}
