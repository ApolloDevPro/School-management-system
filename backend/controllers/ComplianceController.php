<?php
declare(strict_types=1);

final class ComplianceController extends Controller
{
    public function index(): void
    {
        $schoolId = school_id();
        $this->render('compliance/index', [
            'pageTitle'   => 'Government compliance',
            'activeNav'   => 'compliance',
            'emisReturns' => ComplianceModule::emisReturns($schoolId),
            'grants'      => ComplianceModule::grants($schoolId),
            'years'       => ComplianceModule::years($schoolId),
            'terms'       => ComplianceModule::terms($schoolId),
            'canAdd'      => Auth::can('compliance', 'add'),
            'canEdit'     => Auth::can('compliance', 'edit'),
            'canApprove'  => Auth::can('compliance', 'approve'),
        ]);
    }

    public function createEmisReturn(): void
    {
        $schoolId   = school_id();
        $userId     = (int)Auth::user()['id'];
        $yearId     = (int)($_POST['academic_year_id'] ?? 0);
        $termId     = (int)($_POST['term_id'] ?? 0);
        $returnType = (string)($_POST['return_type'] ?? 'EMIS_termly');
        $label      = trim((string)($_POST['period_label'] ?? ''));
        $learners   = (int)($_POST['total_learners'] ?? 0);
        $teachers   = (int)($_POST['total_teachers'] ?? 0);
        $support    = (int)($_POST['total_support_staff'] ?? 0);

        $allowed = ['EMIS_termly', 'EMIS_annual', 'census', 'inspection', 'other'];
        $errors  = [];
        if (!in_array($returnType, $allowed, true)) { $errors['return_type']   = 'Select a valid return type.'; }
        if ($label === '' || mb_strlen($label) > 60)  { $errors['period_label']  = 'Enter a period label (max 60 chars).'; }
        if ($yearId < 1)                               { $errors['academic_year_id'] = 'Select an academic year.'; }
        if ($learners < 0)                             { $errors['total_learners'] = 'Learner count must be 0 or more.'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'compliance', [], $errors);
        }

        $id = ComplianceModule::createEmisReturn([
            'school_id'    => $schoolId,
            'year_id'      => $yearId,
            'term_id'      => $termId,
            'return_type'  => $returnType,
            'period_label' => $label,
            'learners'     => $learners,
            'teachers'     => $teachers,
            'support'      => $support,
            'user_id'      => $userId,
        ]);

        audit('compliance', 'emis_return_created', 'emis_returns', $id, 'EMIS return created: ' . $label);
        $this->respond(true, 'EMIS return draft saved.', 'compliance');
    }

    public function validateEmisReturn(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        $userId   = (int)Auth::user()['id'];

        $ok = ComplianceModule::validateEmisReturn($id, $schoolId, $userId);
        if ($ok) {
            audit('compliance', 'emis_return_validated', 'emis_returns', $id, 'EMIS return validated');
        }
        $this->respond($ok, $ok ? 'Return marked as validated.' : 'Could not validate — ensure it is still a draft.', 'compliance');
    }

    public function submitEmisReturn(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        $userId   = (int)Auth::user()['id'];

        $submitted = false;
        $endpoint = (string)Setting::get($schoolId, 'emis.external_api_endpoint', '');
        if ($endpoint !== '') {
            $emis = ComplianceModule::emisReturn($id, $schoolId);
            if ($emis) {
                $result = self::postToExternal($endpoint, [
                    'return_id' => $id,
                    'school_id' => $schoolId,
                    'return_type' => $emis['return_type'] ?? '',
                    'period_label' => $emis['period_label'] ?? '',
                    'total_learners' => $emis['total_learners'] ?? 0,
                    'total_teachers' => $emis['total_teachers'] ?? 0,
                    'submitted_by' => $userId,
                    'submitted_at' => date('Y-m-d H:i:s'),
                ]);
                if ($result['ok']) {
                    $submitted = ComplianceModule::submitEmisReturn($id, $schoolId, $userId);
                    audit('compliance', 'emis_return_submitted', 'emis_returns', $id, 'EMIS return submitted to MoES external API');
                } else {
                    audit('compliance', 'emis_return_submitted', 'emis_returns', $id, 'MoES external API failed: ' . $result['error']);
                    $this->respond(false, 'External MoES submission failed: ' . $result['error'], 'compliance');
                    return;
                }
            }
        }

        if (!$submitted) {
            $submitted = ComplianceModule::submitEmisReturn($id, $schoolId, $userId);
        }

        if ($submitted) {
            audit('compliance', 'emis_return_submitted', 'emis_returns', $id, 'EMIS return submitted to MoES.');
            $this->respond(true, 'Return submitted.', 'compliance');
        }
        $this->respond(false, 'Could not submit — ensure it is validated first.', 'compliance');
    }

    private static function postToExternal(string $url, array $payload): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'error' => $err, 'http_code' => $httpCode];
        }
        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'response' => $response, 'http_code' => $httpCode];
    }

    public function createGrant(): void
    {
        $schoolId     = school_id();
        $yearId       = (int)($_POST['academic_year_id'] ?? 0);
        $termId       = (int)($_POST['term_id'] ?? 0);
        $type         = (string)($_POST['grant_type'] ?? '');
        $amtExpected  = (float)($_POST['amount_expected'] ?? 0);
        $count        = (int)($_POST['beneficiary_count'] ?? 0);
        $rate         = (float)($_POST['per_learner_rate'] ?? 0);
        $reference    = trim((string)($_POST['reference_number'] ?? ''));

        $errors = [];
        if (!in_array($type, ['UPE', 'USE', 'other_grant'], true)) { $errors['grant_type']      = 'Select a valid grant type.'; }
        if ($yearId < 1)                                             { $errors['academic_year_id'] = 'Select an academic year.'; }
        if ($amtExpected < 0)                                        { $errors['amount_expected']  = 'Amount must be 0 or more.'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'compliance', [], $errors);
        }

        $id = ComplianceModule::createGrant([
            'school_id'       => $schoolId,
            'type'            => $type,
            'year_id'         => $yearId,
            'term_id'         => $termId,
            'amount_expected' => $amtExpected,
            'beneficiary_count' => $count > 0 ? $count : null,
            'per_learner_rate'  => $rate > 0 ? $rate : null,
            'reference'       => $reference ?: null,
        ]);

        audit('compliance', 'grant_created', 'capitation_grants', $id, $type . ' grant record created');
        $this->respond(true, $type . ' grant record created.', 'compliance');
    }

    public function recordGrantReceipt(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();
        $userId   = (int)Auth::user()['id'];
        $amount   = (float)($_POST['amount_received'] ?? 0);
        $date     = trim((string)($_POST['received_date'] ?? ''));

        $errors = [];
        if ($amount <= 0)   { $errors['amount_received'] = 'Enter the amount received (greater than 0).'; }
        if ($date === '')   { $errors['received_date']   = 'Enter the date received.'; }
        if ($errors) {
            $this->respond(false, 'Please correct the highlighted fields.', 'compliance', [], $errors);
        }

        $ok = ComplianceModule::recordGrantReceipt($id, $schoolId, $amount, $date, $userId);
        if ($ok) {
            audit('compliance', 'grant_receipt_recorded', 'capitation_grants', $id, 'Receipt of ' . number_format($amount) . ' recorded');
        }
        $this->respond($ok, $ok ? 'Receipt recorded.' : 'Could not record receipt.', 'compliance');
    }
}
