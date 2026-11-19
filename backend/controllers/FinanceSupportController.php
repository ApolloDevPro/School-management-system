<?php
declare(strict_types=1);

/**
 * Sponsors, bursaries, discounts and fee waivers.
 *
 * Records adjustments, approves them and applies them to student invoices.
 */
final class FinanceSupportController extends Controller
{
    private const FUNDING_SOURCES = ['school', 'government', 'donor', 'pta', 'other'];
    private const ADJUSTMENT_TYPES = ['discount', 'waiver', 'bursary_offset'];
    private const REASONS = ['sibling', 'staff_child', 'scholarship', 'merit', 'hardship', 'early_payment', 'other'];

    public function index(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $tab = (string)($_GET['tab'] ?? 'bursaries');
        $this->render('finance/support', [
            'pageTitle' => 'Bursaries, sponsors and waivers',
            'activeNav' => 'finance',
            'tab' => in_array($tab, ['bursaries', 'discounts', 'sponsors'], true) ? $tab : 'bursaries',
            'years' => FinanceSupportModule::years($schoolId),
            'terms' => FinanceSupportModule::terms($schoolId),
            'classes' => FinanceSupportModule::classes($schoolId),
            'students' => FinanceSupportModule::students($schoolId),
            'sponsors' => FinanceSupportModule::sponsors($schoolId),
            'bursaries' => FinanceSupportModule::bursaries($schoolId),
            'awards' => FinanceSupportModule::awards($schoolId),
            'discounts' => FinanceSupportModule::discounts($schoolId),
            'canAdd' => Auth::can('finance', 'add'),
            'canEdit' => Auth::can('finance', 'edit'),
            'canApprove' => Auth::can('finance', 'approve'),
        ]);
    }

    public function saveSponsor(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['sponsor_type'] ?? 'company');
        if ($name === '' || mb_strlen($name) > 160 || !in_array($type, ['ngo', 'church', 'company', 'government', 'individual', 'other'], true)) {
            $this->respond(false, 'Enter a sponsor name and choose a valid sponsor type.', 'finance_support', ['tab' => 'sponsors']);
        }
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(false, 'Enter a valid sponsor email address.', 'finance_support', ['tab' => 'sponsors']);
        }
        $data = [
            'name' => $name,
            'sponsor_type' => $type,
            'contact_person' => $this->line($_POST['contact_person'] ?? '', 140),
            'phone' => $this->line($_POST['phone'] ?? '', 40),
            'email' => $email !== '' ? $email : null,
            'address' => $this->line($_POST['address'] ?? '', 255),
            'payment_terms' => $this->line($_POST['payment_terms'] ?? '', 120),
            'notes' => $this->line($_POST['notes'] ?? '', 255),
            'status' => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
        $id = FinanceSupportModule::saveSponsor(school_id(), $data);
        audit('finance', 'create', 'sponsors', $id, 'Saved sponsor ' . $name);
        $this->respond(true, 'Sponsor saved.', 'finance_support', ['tab' => 'sponsors']);
    }

    public function saveBursary(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $source = (string)($_POST['funding_source'] ?? 'donor');
        $coverage = (string)($_POST['coverage_type'] ?? 'partial');
        $status = (string)($_POST['status'] ?? 'open');
        if ($name === '' || mb_strlen($name) > 140
            || !in_array($source, self::FUNDING_SOURCES, true)
            || !in_array($coverage, ['full', 'partial'], true)
            || !in_array($status, ['open', 'closed', 'suspended'], true)) {
            $this->respond(false, 'Enter a bursary name and choose valid funding, coverage and status options.', 'finance_support');
        }
        $budget = null;
        if (($_POST['annual_budget'] ?? '') !== '') {
            if (!is_numeric($_POST['annual_budget']) || (float)$_POST['annual_budget'] < 0) {
                $this->respond(false, 'Enter a valid annual budget.', 'finance_support');
            }
            $budget = round((float)$_POST['annual_budget'], 2);
        }
        $sponsorId = (int)($_POST['sponsor_id'] ?? 0);
        if ($sponsorId > 0 && !FinanceSupportModule::sponsor($sponsorId, school_id())) {
            $this->respond(false, 'Choose a sponsor from this school.', 'finance_support');
        }
        $data = [
            'name' => $name,
            'sponsor_id' => $sponsorId > 0 ? $sponsorId : null,
            'funding_source' => $source,
            'coverage_type' => $coverage,
            'description' => $this->line($_POST['description'] ?? '', 255),
            'annual_budget' => $budget,
            'start_date' => $this->dateOrNull($_POST['start_date'] ?? ''),
            'end_date' => $this->dateOrNull($_POST['end_date'] ?? ''),
            'status' => $status,
        ];
        $id = FinanceSupportModule::saveBursary(school_id(), $data);
        audit('finance', 'create', 'bursaries', $id, 'Saved bursary ' . $name);
        $this->respond(true, 'Bursary saved.', 'finance_support');
    }

    public function saveAward(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $type = (string)($_POST['award_type'] ?? 'amount');
        $value = is_numeric($_POST['award_value'] ?? null) ? round((float)$_POST['award_value'], 2) : -1;
        if (!in_array($type, ['amount', 'percent'], true) || $value <= 0 || ($type === 'percent' && $value > 100)) {
            $this->respond(false, 'Enter a positive award value; a percentage cannot exceed 100.', 'finance_support');
        }
        $data = [
            'bursary_id' => (int)($_POST['bursary_id'] ?? 0),
            'student_id' => (int)($_POST['student_id'] ?? 0),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'term_id' => (int)($_POST['term_id'] ?? 0),
            'award_type' => $type,
            'award_value' => $value,
            'award_amount' => $type === 'amount' ? $value : 0,
            'start_date' => $this->dateOrNull($_POST['start_date'] ?? ''),
            'end_date' => $this->dateOrNull($_POST['end_date'] ?? ''),
            'notes' => $this->line($_POST['notes'] ?? '', 255),
        ];
        try {
            $id = FinanceSupportModule::saveAward(school_id(), $data, (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'finance_support');
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $this->respond(false, 'This student already has that bursary for the selected year and term.', 'finance_support');
            }
            throw $error;
        }
        audit('finance', 'create', 'bursary_awards', $id, 'Awarded bursary to student #' . $data['student_id']);
        $this->respond(true, 'Bursary award recorded. Apply it to an invoice when you are ready.', 'finance_support');
    }

    public function saveDiscount(): void
    {
        if (!$this->allowed('finance', 'add')) {
            return;
        }
        $adjustment = (string)($_POST['adjustment_type'] ?? 'discount');
        $reason = (string)($_POST['reason'] ?? 'other');
        $valueType = (string)($_POST['value_type'] ?? 'amount');
        $value = is_numeric($_POST['value'] ?? null) ? round((float)$_POST['value'], 2) : -1;
        if (!in_array($adjustment, self::ADJUSTMENT_TYPES, true)
            || !in_array($reason, self::REASONS, true)
            || !in_array($valueType, ['amount', 'percent'], true)
            || $value <= 0 || ($valueType === 'percent' && $value > 100)) {
            $this->respond(false, 'Choose a valid adjustment and enter a positive value.', 'finance_support', ['tab' => 'discounts']);
        }
        $maxAmount = null;
        if (($_POST['max_amount'] ?? '') !== '') {
            if (!is_numeric($_POST['max_amount']) || (float)$_POST['max_amount'] <= 0) {
                $this->respond(false, 'Enter a positive maximum amount or leave it blank.', 'finance_support', ['tab' => 'discounts']);
            }
            $maxAmount = round((float)$_POST['max_amount'], 2);
        }
        $data = [
            'student_id' => (int)($_POST['student_id'] ?? 0),
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'academic_year_id' => (int)($_POST['academic_year_id'] ?? 0),
            'term_id' => (int)($_POST['term_id'] ?? 0),
            'adjustment_type' => $adjustment,
            'reason' => $reason,
            'description' => $this->line($_POST['description'] ?? '', 255),
            'value_type' => $valueType,
            'value' => $value,
            'max_amount' => $maxAmount,
            'expires_on' => $this->dateOrNull($_POST['expires_on'] ?? ''),
        ];
        try {
            $id = FinanceSupportModule::saveDiscount(school_id(), $data, (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'finance_support', ['tab' => 'discounts']);
        }
        audit('finance', 'create', 'discounts_waivers', $id, 'Recorded ' . $adjustment . ' awaiting approval');
        $this->respond(true, 'Adjustment recorded. Approve and apply it to an invoice.', 'finance_support', ['tab' => 'discounts']);
    }

    public function approveDiscount(): void
    {
        if (!$this->allowed('finance', 'approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            FinanceSupportModule::approveDiscount($id, school_id(), (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'finance_support', ['tab' => 'discounts']);
        }
        audit('finance', 'approve', 'discounts_waivers', $id, 'Approved fee adjustment #' . $id);
        $this->respond(true, 'Adjustment approved.', 'finance_support', ['tab' => 'discounts']);
    }

    public function applyDiscount(): void
    {
        if (!$this->allowed('finance', 'approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            $result = FinanceSupportModule::applyDiscount($id, school_id(), (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'finance_support', ['tab' => 'discounts']);
        }
        audit('finance', 'update', 'discounts_waivers', $id, 'Applied adjustment to invoice ' . $result['invoice_number']);
        $this->respond(
            true,
            'Adjustment of ' . number_format($result['amount'], 0) . ' applied to invoice '
                . $result['invoice_number'] . '. New balance: ' . number_format($result['balance'], 0) . '.',
            'finance_support',
            ['tab' => 'discounts']
        );
    }

    public function applyAward(): void
    {
        if (!$this->allowed('finance', 'approve')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        try {
            $result = FinanceSupportModule::applyAward($id, school_id(), (int)Auth::user()['id']);
        } catch (DomainException $error) {
            $this->respond(false, $error->getMessage(), 'finance_support');
        }
        audit('finance', 'update', 'bursary_awards', $id, 'Applied bursary award to invoice ' . $result['invoice_number']);
        $this->respond(
            true,
            'Bursary of ' . number_format($result['amount'], 0) . ' applied to invoice '
                . $result['invoice_number'] . '.',
            'finance_support'
        );
    }

    private function line(mixed $value, int $max): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function dateOrNull(mixed $value): ?string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) && strtotime($text) ? $text : null;
    }
}
