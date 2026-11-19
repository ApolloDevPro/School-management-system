<?php
declare(strict_types=1);

final class GateController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('gate/index', [
            'pageTitle' => 'Gate & visitors',
            'activeNav' => 'gate',
            'visitors'  => GateModule::visitors($schoolId),
            'canAdd'    => Auth::can('gate', 'add'),
            'canEdit'   => Auth::can('gate', 'edit'),
        ]);
    }

    public function checkIn(): void
    {
        $name    = trim((string)($_POST['full_name'] ?? ''));
        $host    = trim((string)($_POST['host_name'] ?? ''));
        $purpose = trim((string)($_POST['purpose'] ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 160)    { $errors['full_name'] = "Enter the visitor's full name."; }
        if ($host === '' || mb_strlen($host) > 160)    { $errors['host_name'] = 'Enter who they are visiting.'; }
        if ($purpose === '' || mb_strlen($purpose) > 255) { $errors['purpose']   = 'Enter the purpose of visit.'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'gate', [], $errors);
        }

        $id = GateModule::createVisitor([
            'school_id'    => school_id(),
            'name'         => $name,
            'phone'        => trim((string)($_POST['phone'] ?? '')) ?: null,
            'organisation' => trim((string)($_POST['organisation'] ?? '')) ?: null,
            'host'         => $host,
            'purpose'      => $purpose,
            'plate'        => trim((string)($_POST['vehicle_plate'] ?? '')) ?: null,
            'badge'        => trim((string)($_POST['badge_number'] ?? '')) ?: null,
            'user_id'      => (int)Auth::user()['id'],
        ]);

        audit('gate', 'visitor_checked_in', 'visitors', $id, 'Visitor checked in: ' . $name);
        $this->respond(true, $name . ' checked in successfully.', 'gate');
    }

    public function checkOut(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $userId = (int)Auth::user()['id'];
        $ok     = GateModule::checkOut($id, school_id(), $userId);
        $this->respond($ok, $ok ? 'Visitor checked out.' : 'Could not check out (visitor may already be out).', 'gate');
    }

    public function gatePass(): void
    {
        $schoolId = school_id();
        $visitorId = (int)($_GET['visitor_id'] ?? 0);
        $db = Database::conn();
        $visitor = Database::query(
            'SELECT * FROM visitors WHERE id = ? AND school_id = ?',
            [$visitorId, $schoolId]
        )->fetch();
        if (!$visitor) {
            $this->respond(false, 'Visitor not found.', 'gate');
            return;
        }
        $school = school_row() ?? [];
        $html = PdfService::gatePassHtml($school, [
            'visitor_name' => $visitor['full_name'] ?? '-',
            'phone' => $visitor['phone'] ?? '-',
            'host_name' => $visitor['host_name'] ?? '-',
            'purpose' => $visitor['purpose'] ?? '-',
            'time_out' => $visitor['time_out'] ?? $visitor['time_in'] ?? '-',
            'vehicle_reg' => $visitor['vehicle_plate'] ?? '-',
        ]);
        PdfService::output($html, 'gate_pass_'.$visitorId.'.pdf');
    }
}
