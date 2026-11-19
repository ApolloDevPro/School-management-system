<?php
declare(strict_types=1);

/**
 * Message templates, bulk campaigns and PTA meetings.
 *
 * Campaigns snapshot recipients and stay queued until a gateway is marked as
 * sent, except portal campaigns which count as delivered immediately.
 */
final class NotificationCenterController extends Controller
{
    private const CATEGORIES = ['fees', 'attendance', 'results', 'announcement', 'event', 'emergency', 'reminder', 'general', 'canteen', 'transport'];
    private const AUDIENCES = ['guardians', 'students', 'staff', 'all'];
    private const CHANNELS = ['sms', 'email', 'portal'];

    public function index(): void
    {
        if (!$this->allowed('communication', 'view')) {
            return;
        }
        $schoolId = school_id();
        $tab = (string)($_GET['tab'] ?? 'campaigns');
        $campaignId = (int)($_GET['campaign_id'] ?? 0);
        $meetingId = (int)($_GET['meeting_id'] ?? 0);
        $templates = NotificationCenter::templates($schoolId);
        $editTemplateId = (int)($_GET['edit_template'] ?? 0);
        $editTemplate = null;
        foreach ($templates as $template) {
            if ((int)$template['id'] === $editTemplateId) {
                $editTemplate = $template;
                break;
            }
        }
        $this->render('communication/center', [
            'pageTitle' => 'Campaigns and PTA',
            'activeNav' => 'communication',
            'tab' => in_array($tab, ['campaigns', 'templates', 'pta'], true) ? $tab : 'campaigns',
            'templates' => $templates,
            'editTemplate' => $editTemplate,
            'campaigns' => NotificationCenter::campaigns($schoolId),
            'selectedCampaign' => $campaignId ? NotificationCenter::campaign($campaignId, $schoolId) : null,
            'recipients' => $campaignId ? NotificationCenter::recipients($campaignId) : [],
            'meetings' => NotificationCenter::meetings($schoolId),
            'selectedMeeting' => $meetingId ? NotificationCenter::meeting($meetingId, $schoolId) : null,
            'attendance' => $meetingId ? NotificationCenter::meetingAttendance($meetingId, $schoolId) : [],
            'years' => AcademicYear::forSchool($schoolId),
            'terms' => FinanceModel::termsForSchool($schoolId),
            'classes' => FinanceSupportModule::classes($schoolId),
            'guardians' => Database::query(
                'SELECT id, full_name, first_name, last_name FROM guardians WHERE school_id = ? ORDER BY last_name, first_name',
                [$schoolId]
            )->fetchAll(),
            'canAdd' => Auth::can('communication', 'add'),
            'canEdit' => Auth::can('communication', 'edit'),
            'canApprove' => Auth::can('communication', 'approve'),
        ]);
    }

    public function saveTemplate(): void
    {
        if (!$this->allowed('communication', 'add')) {
            return;
        }
        $code = strtoupper(trim((string)($_POST['template_code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $channel = (string)($_POST['channel'] ?? 'sms');
        $body = trim((string)($_POST['body'] ?? ''));
        if (!preg_match('/^[A-Z0-9_-]{2,60}$/', $code) || $name === '' || mb_strlen($name) > 120
            || !in_array($channel, self::CHANNELS, true) || $body === '') {
            $this->respond(false, 'Enter a code, name, channel and message body.', 'communication/center', ['tab' => 'templates']);
        }
        $data = [
            'id' => (int)($_POST['id'] ?? 0),
            'template_code' => $code,
            'name' => $name,
            'channel' => $channel,
            'subject' => $this->text($_POST['subject'] ?? '', 180),
            'body' => mb_substr($body, 0, 4000),
            'placeholders' => $this->text($_POST['placeholders'] ?? '', 255),
            'related_module' => $this->text($_POST['related_module'] ?? '', 40),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        try {
            $id = NotificationCenter::saveTemplate(school_id(), $data, (int)Auth::user()['id']);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'A template with that code and channel already exists.', 'communication/center', ['tab' => 'templates']);
            }
            throw $error;
        }
        audit('communication', 'template_saved', 'notification_templates', $id, 'Saved template ' . $code);
        $this->respond(true, 'Template saved.', 'communication/center', ['tab' => 'templates']);
    }

    public function sendCampaign(): void
    {
        if (!$this->allowed('communication', 'add')) {
            return;
        }
        $channel = (string)($_POST['channel'] ?? 'sms');
        $audience = (string)($_POST['audience'] ?? 'guardians');
        $category = (string)($_POST['category'] ?? 'general');
        $priority = (string)($_POST['priority'] ?? 'normal');
        $message = trim((string)($_POST['message'] ?? ''));
        if (!in_array($channel, self::CHANNELS, true) || !in_array($audience, self::AUDIENCES, true)
            || !in_array($category, self::CATEGORIES, true)
            || !in_array($priority, ['low', 'normal', 'high', 'urgent'], true)
            || $message === '') {
            $this->respond(false, 'Choose a channel, audience and category, then enter the message.', 'communication/center');
        }
        $scheduled = null;
        $scheduledRaw = trim((string)($_POST['scheduled_for'] ?? ''));
        if ($scheduledRaw !== '') {
            $timestamp = strtotime($scheduledRaw);
            if ($timestamp === false) {
                $this->respond(false, 'Enter a valid schedule date and time.', 'communication/center');
            }
            $scheduled = date('Y-m-d H:i:s', $timestamp);
        }
        $data = [
            'template_id' => (int)($_POST['template_id'] ?? 0),
            'channel' => $channel,
            'category' => $category,
            'audience' => $audience,
            'class_id' => $audience === 'guardians' && (int)($_POST['class_id'] ?? 0) > 0 ? (int)$_POST['class_id'] : null,
            'subject' => $this->text($_POST['subject'] ?? '', 180),
            'message' => mb_substr($message, 0, 4000),
            'is_emergency' => isset($_POST['is_emergency']) ? 1 : 0,
            'priority' => $priority,
            'scheduled_for' => $scheduled,
        ];
        try {
            $result = NotificationCenter::sendCampaign(school_id(), $data, (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'communication/center');
        }
        audit('communication', 'campaign', 'notifications', $result['id'], 'Queued ' . $channel . ' campaign to ' . $result['recipients'] . ' recipient(s)');
        $this->respond(
            true,
            'Campaign created for ' . $result['recipients'] . ' recipient(s). '
                . ($channel === 'portal' ? 'It is available in the portal.' : 'Download the recipient list for your SMS or email gateway, then mark it sent.'),
            'communication/center'
        );
    }

    public function markCampaignSent(): void
    {
        if (!$this->allowed('communication', 'approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            NotificationCenter::markCampaignSent($id, school_id(), (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'communication/center');
        }
        audit('communication', 'campaign_sent', 'notifications', $id, 'Marked campaign #' . $id . ' as sent');
        $this->respond(true, 'Campaign marked as sent.', 'communication/center');
    }

    public function cancelCampaign(): void
    {
        if (!$this->allowed('communication', 'approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            NotificationCenter::cancelCampaign($id, school_id());
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'communication/center');
        }
        audit('communication', 'campaign_cancelled', 'notifications', $id, 'Cancelled campaign #' . $id);
        $this->respond(true, 'Campaign cancelled.', 'communication/center');
    }

    /**
     * Download destinations for a queued campaign, ready for an SMS or email
     * gateway upload.
     */
    public function campaignRecipients(): void
    {
        if (!$this->allowed('communication', 'view')) {
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        $campaign = NotificationCenter::campaign($id, school_id());
        if (!$campaign) {
            flash_set('danger', 'Campaign not found.');
            redirect('communication/center');
        }
        $filename = 'campaign-' . $id . '-recipients.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Type', 'Destination', 'Status', 'Subject', 'Message']);
        foreach (NotificationCenter::recipients($id) as $recipient) {
            fputcsv($out, [
                $recipient['name'], $recipient['recipient_type'], $recipient['destination'],
                $recipient['delivery_status'], $campaign['subject'], $campaign['message'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function saveMeeting(): void
    {
        if (!$this->allowed('communication', 'add')) {
            return;
        }
        $title = trim((string)($_POST['title'] ?? ''));
        $date = trim((string)($_POST['meeting_date'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $this->respond(false, 'Enter a meeting title and a valid meeting date.', 'communication/center', ['tab' => 'pta']);
        }
        $data = [
            'id' => (int)($_POST['id'] ?? 0),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'term_id' => (int)($_POST['term_id'] ?? 0),
            'title' => $title,
            'meeting_date' => $date,
            'start_time' => $this->timeOrNull($_POST['start_time'] ?? ''),
            'venue' => $this->text($_POST['venue'] ?? '', 160),
            'agenda' => $this->text($_POST['agenda'] ?? '', 2000),
            'minutes_path' => $this->text($_POST['minutes_path'] ?? '', 255),
            'resolutions' => $this->text($_POST['resolutions'] ?? '', 2000),
            'contributions_agreed' => $this->moneyOrNull($_POST['contributions_agreed'] ?? ''),
            'contributions_received' => $this->moneyOrNull($_POST['contributions_received'] ?? ''),
            'chairperson' => $this->text($_POST['chairperson'] ?? '', 140),
            'secretary' => $this->text($_POST['secretary'] ?? '', 140),
            'status' => in_array($_POST['status'] ?? '', ['planned', 'held', 'minutes_recorded', 'closed'], true)
                ? (string)$_POST['status'] : 'planned',
        ];
        $years = AcademicYear::forSchool(school_id());
        $validYear = array_filter($years, static fn (array $year): bool => (int)$year['id'] === $data['academic_year_id']);
        if (!$data['academic_year_id'] || !$validYear) {
            $this->respond(false, 'Choose an academic year for this school.', 'communication/center', ['tab' => 'pta']);
        }
        try {
            $id = NotificationCenter::saveMeeting(school_id(), $data, (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'communication/center', ['tab' => 'pta']);
        }
        audit('communication', 'pta_saved', 'pta_meetings', $id, 'Saved PTA meeting ' . $title);
        $this->respond(true, 'PTA meeting saved.', 'communication/center', ['tab' => 'pta', 'meeting_id' => $id]);
    }

    public function signInMeeting(): void
    {
        if (!$this->allowed('communication', 'add')) {
            return;
        }
        $meetingId = (int)($_POST['meeting_id'] ?? 0);
        $guardianId = (int)($_POST['guardian_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($meetingId < 1 || $guardianId < 1) {
            $this->respond(false, 'Choose a meeting and a guardian.', 'communication/center', ['tab' => 'pta']);
        }
        try {
            $id = NotificationCenter::signIn($meetingId, school_id(), [
                'guardian_id' => $guardianId,
                'student_id' => $studentId,
                'is_proxy' => isset($_POST['is_proxy']),
                'proxy_name' => $this->text($_POST['proxy_name'] ?? '', 140),
            ]);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'communication/center', ['tab' => 'pta', 'meeting_id' => $meetingId]);
        }
        audit('communication', 'pta_attendance', 'pta_attendance', $id, 'PTA sign-in for meeting #' . $meetingId);
        $this->respond(true, 'Guardian signed in.', 'communication/center', ['tab' => 'pta', 'meeting_id' => $meetingId]);
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function timeOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        return preg_match('/^\d{2}:\d{2}$/', $text) ? $text . ':00' : null;
    }

    private function moneyOrNull(mixed $value): ?float
    {
        $text = trim((string)$value);
        if ($text === '' || !is_numeric($text) || (float)$text < 0) {
            return null;
        }
        return round((float)$text, 2);
    }
}
