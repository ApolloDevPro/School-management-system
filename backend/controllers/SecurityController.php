<?php
declare(strict_types=1);

/**
 * Support tickets.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class SecurityController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $userId   = (int)Auth::user()['id'];
        $isAdmin  = Auth::can('administration', 'view');

        $this->render('security/index', [
            'pageTitle' => 'Support tickets',
            'activeNav' => 'security',
            'tickets'   => SecurityModule::tickets($schoolId, $userId, $isAdmin),
            'isAdmin'   => $isAdmin,
            'canAdd'    => true,  // any authenticated user can open a ticket
        ]);
    }

    public function createTicket(): void
    {
        $userId   = (int)Auth::user()['id'];
        $schoolId = school_id();
        $category = (string)($_POST['category'] ?? 'general');
        $subject  = trim((string)($_POST['subject'] ?? ''));
        $desc     = trim((string)($_POST['description'] ?? ''));
        $priority = (string)($_POST['priority'] ?? 'normal');

        $errors = [];
        if ($subject === '' || mb_strlen($subject) > 255) { $errors['subject']     = 'Enter a subject (max 255 chars).'; }
        if ($desc === '')                                  { $errors['description'] = 'Describe the issue.'; }
        if (!in_array($category, ['general','technical','access','data','other'], true)) { $category = 'general'; }
        if (!in_array($priority, ['low','normal','high','urgent'], true))                { $priority = 'normal'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'security', [], $errors);
        }

        $number = 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
        $id = SecurityModule::createTicket([
            'number'      => $number,
            'school_id'   => $schoolId,
            'user_id'     => $userId,
            'category'    => $category,
            'subject'     => $subject,
            'description' => $desc,
            'priority'    => $priority,
        ]);

        audit('security', 'ticket_created', 'support_tickets', $id, 'Support ticket ' . $number . ' opened');
        $this->respond(true, 'Ticket ' . $number . ' submitted.', 'security');
    }

    public function updateTicket(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $status   = (string)($_POST['status'] ?? '');
        $userId   = (int)Auth::user()['id'];
        $schoolId = school_id();
        $isAdmin  = Auth::can('administration', 'view');

        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            $this->respond(false, 'Invalid status.', 'security');
        }
        // non-admins can only close their own tickets
        if (!$isAdmin && !SecurityModule::owns($id, $schoolId, $userId)) {
            $this->respond(false, 'You can only manage your own tickets.', 'security');
        }

        $ok = SecurityModule::transition($id, $schoolId, $userId, $isAdmin, $status);
        $this->respond($ok, $ok ? 'Ticket status updated to ' . str_replace('_',' ',$status) . '.' : 'Could not update ticket.', 'security');
    }
}
