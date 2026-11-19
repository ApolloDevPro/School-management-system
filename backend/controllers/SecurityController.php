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

    /**
     * Admin screen listing every active sign-in for the school.
     */
    public function sessions(): void
    {
        $sessions = [];
        $trackingError = null;
        try {
            $sessions = SecurityModule::sessions(school_id());
        } catch (Throwable) {
            $trackingError = 'Session tracking is not installed yet. Run database/18_portal_security_module.sql, then reload this page.';
        }

        $this->render('security/sessions', [
            'pageTitle'     => 'Active sessions',
            'activeNav'     => 'security_sessions',
            'sessions'      => $sessions,
            'trackingError' => $trackingError,
            'currentHash'   => hash('sha256', (string)($_SESSION['session_token'] ?? '')),
            'canRevoke'     => Auth::can('security', 'edit'),
        ]);
    }

    /**
     * End one session chosen from the list.
     */
    public function revokeSession(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        try {
            $session = SecurityModule::session($id, $schoolId);
            if (!$session) {
                $this->respond(false, 'That session has already ended.', 'security/sessions');
            }
            if (hash_equals((string)$session['session_token_hash'], hash('sha256', (string)($_SESSION['session_token'] ?? '')))) {
                $this->respond(false, 'Use Sign out to end your own current session.', 'security/sessions');
            }

            $ok = SecurityModule::revoke($id, $schoolId);
        } catch (Throwable) {
            $this->respond(false, 'Session tracking is not available.', 'security/sessions');
        }

        if ($ok) {
            audit('security', 'session_revoked', 'portal_sessions', $id, 'Ended an active session for ' . $session['full_name']);
        }
        $this->respond($ok, $ok ? 'Session ended. That device will be signed out on its next request.' : 'Could not end that session.', 'security/sessions');
    }

    /**
     * End all of the signed-in user's other sessions, keeping this browser.
     */
    public function revokeOthers(): void
    {
        $user = Auth::user();
        if (!$user) {
            redirect('login');
        }
        $token = (string)($_SESSION['session_token'] ?? '');
        if ($token === '') {
            $this->respond(false, 'This session is not tracked yet. Refresh the page and try again.', 'security/sessions');
        }

        try {
            $count = SecurityModule::revokeOthers((int)$user['id'], school_id(), hash('sha256', $token));
        } catch (Throwable) {
            $this->respond(false, 'Session tracking is not available.', 'security/sessions');
        }
        if ($count > 0) {
            audit('security', 'sessions_revoked', 'portal_sessions', null, 'Ended ' . $count . ' other session(s) for this account');
        }
        $this->respond(
            true,
            $count > 0 ? $count . ' other session(s) ended.' : 'No other sessions were active.',
            'security/sessions'
        );
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
