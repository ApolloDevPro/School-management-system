<?php
declare(strict_types=1);

final class PortalController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $userId   = (int)Auth::user()['id'];

        $profile  = PortalModule::profile($userId, $schoolId);

        // Build dashboard data depending on user type
        $studentData  = null;
        $guardianData = null;
        $notices      = PortalModule::recentNotices($schoolId);
        $consents     = $profile ? PortalModule::consents($schoolId, $profile) : [];

        if ($profile && $profile['user_type'] === 'student' && $profile['student_id']) {
            $studentData = PortalModule::studentSummary((int)$profile['student_id'], $schoolId);
        } elseif ($profile && $profile['user_type'] === 'parent' && $profile['guardian_id']) {
            $guardianData = PortalModule::guardianSummary((int)$profile['guardian_id'], $schoolId);
        }

        $this->render('portal/index', [
            'pageTitle'    => 'My portal',
            'activeNav'    => 'portal',
            'profile'      => $profile,
            'studentData'  => $studentData,
            'guardianData' => $guardianData,
            'notices'      => $notices,
            'consents'     => $consents,
        ]);
    }

    public function updateConsent(): void
    {
        $schoolId = school_id();
        $userId   = (int)Auth::user()['id'];
        $id       = (int)($_POST['id'] ?? 0);
        $granted  = (bool)($_POST['granted'] ?? false);

        $profile = PortalModule::profile($userId, $schoolId);
        if (!$profile) {
            $this->respond(false, 'No portal profile found.', 'portal');
        }

        $ok = PortalModule::setConsent($id, $schoolId, $profile, $granted);
        audit('portal', 'consent_updated', 'consent_records', $id, 'Consent ' . ($granted ? 'granted' : 'withdrawn'));
        $this->respond($ok, $ok ? 'Consent updated.' : 'Could not update consent.', 'portal');
    }
}
