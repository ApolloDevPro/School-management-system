<?php
declare(strict_types=1);

/**
 * Announcements, templates and publishing.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class CommunicationController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('communication/index', [
            'pageTitle'     => 'Communication',
            'activeNav'     => 'communication',
            'announcements' => CommunicationModule::announcements($schoolId),
            'canAdd'        => Auth::can('communication', 'add'),
            'canEdit'       => Auth::can('communication', 'edit'),
            'canApprove'    => Auth::can('communication', 'approve'),
        ]);
    }

    public function store(): void
    {
        $title    = trim((string)($_POST['title'] ?? ''));
        $body     = trim((string)($_POST['body'] ?? ''));
        $category = (string)($_POST['category'] ?? 'general');
        $audience = (string)($_POST['audience'] ?? 'all');

        $errors = [];
        if ($title === '' || mb_strlen($title) > 255) { $errors['title'] = 'Enter a title (max 255 chars).'; }
        if ($body === '')                              { $errors['body']  = 'Enter the announcement body.'; }
        if (!in_array($category, ['general','academic','financial','event','emergency','health'], true)) { $category = 'general'; }
        if (!in_array($audience, ['all','students','parents','staff','teachers'], true))                { $audience = 'all'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'communication', [], $errors);
        }

        $id = CommunicationModule::createAnnouncement([
            'school_id' => school_id(),
            'title'     => $title,
            'body'      => $body,
            'category'  => $category,
            'audience'  => $audience,
            'user_id'   => (int)Auth::user()['id'],
        ]);

        audit('communication', 'announcement_created', 'announcements', $id, 'Created: ' . $title);
        $this->respond(true, 'Announcement saved as draft.', 'communication');
    }

    public function publish(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $this->respond(false, 'Invalid status.', 'communication');
        }
        $ok = CommunicationModule::changeStatus($id, school_id(), $status);
        $label = match($status) { 'published' => 'published', 'archived' => 'archived', default => 'reverted to draft' };
        $this->respond($ok, $ok ? "Announcement {$label}." : 'Could not update announcement.', 'communication');
    }
}
