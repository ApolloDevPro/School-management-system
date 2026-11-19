<?php
declare(strict_types=1);

/**
 * Fee structures, invoices, payments, receipts, clearances, bank reconciliation, reminders, online payments and exports.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class FinanceController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('view')) {
            return;
        }
        $schoolId = school_id();
        $years = AcademicYear::forSchool($schoolId);
        $yearId = filter_var($_GET['year_id'] ?? null, FILTER_VALIDATE_INT);
        $yearId = ($yearId !== false && $yearId > 0) ? (int)$yearId : null;
        if ($yearId !== null) {
            $validYear = array_filter($years, static fn (array $year): bool => (int)$year['id'] === $yearId);
            if (!$validYear) {
                $yearId = null;
            }
        }
        $includeAll = (($_GET['show_all'] ?? '') === '1');
        $this->render('finance/index', [
            'pageTitle' => 'Finance and fees',
            'activeNav' => 'finance',
            'students' => Student::listForSchool($schoolId, ['status' => 'active']),
            'years' => $years,
            'terms' => FinanceModel::termsForSchool($schoolId),
            'invoices' => FinanceModel::invoices($schoolId),
            'payments' => FinanceModel::payments($schoolId),
            'methods' => FinanceModel::paymentMethods($schoolId),
            'structures' => FinanceModel::structures($schoolId),
            'structureItems' => FinanceModel::structureItems($schoolId),
            'classes' => FinanceModel::classes($schoolId),
            'balances' => FinanceModel::balances($schoolId, $yearId, $includeAll),
            'balanceYearId' => $yearId,
            'balanceIncludeAll' => $includeAll,
            'clearances' => FinanceModel::clearances($schoolId),
            'reminders' => FinanceModel::reminders($schoolId),
            'receipts' => FinanceModel::receipts($schoolId),
            'canAdd' => Auth::can('finance', 'add'),
            'canEdit' => Auth::can('finance', 'edit'),
            'canApprove' => Auth::can('finance', 'approve'),
        ]);
    }

    public function storeInvoice(): void
    {
        try {
            $amount = $this->amount($_POST['amount'] ?? null, 'Invoice amount');
            $data = [
                'student_id' => $this->positiveInt($_POST['student_id'] ?? null, 'Student'),
                'academic_year_id' => $this->positiveInt($_POST['academic_year_id'] ?? null, 'Academic year'),
                'term_id' => $this->optionalInt($_POST['term_id'] ?? null),
                'issue_date' => $this->date($_POST['issue_date'] ?? null, 'Issue date'),
                'due_date' => $this->optionalDate($_POST['due_date'] ?? null, 'Due date'),
                'item_name' => $this->text($_POST['item_name'] ?? '', 'Fee item', 140),
                'amount' => $amount,
                'notes' => $this->optionalText($_POST['notes'] ?? '', 255),
            ];
            FinanceModel::saveInvoice(school_id(), $data, Auth::user());
            $this->respond(true, 'Fee invoice created.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance');
        }
    }

    public function recordPayment(): void
    {
        try {
            $channel = (string)($_POST['channel'] ?? 'cash');
            if (!in_array($channel, ['cash', 'bank', 'cheque', 'mobile_money', 'online', 'other'], true)) {
                throw new InvalidArgumentException('Choose a valid payment channel.');
            }
            $data = [
                'invoice_id' => $this->positiveInt($_POST['invoice_id'] ?? null, 'Invoice'),
                'payment_method_id' => $this->optionalInt($_POST['payment_method_id'] ?? null),
                'channel' => $channel,
                'amount' => $this->amount($_POST['amount'] ?? null, 'Payment amount'),
                'payment_date' => $this->date($_POST['payment_date'] ?? null, 'Payment date'),
                'external_reference' => $this->optionalText($_POST['external_reference'] ?? '', 80),
                'payer_name' => $this->optionalText($_POST['payer_name'] ?? '', 160),
                'payer_phone' => $this->optionalText($_POST['payer_phone'] ?? '', 40),
                'notes' => $this->optionalText($_POST['notes'] ?? '', 255),
            ];
            FinanceModel::recordPayment(school_id(), $data, Auth::user());
            $this->respond(true, 'Payment recorded and queued for verification.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance');
        }
    }

    public function verifyPayment(): void
    {
        try {
            $paymentId = $this->positiveInt($_POST['payment_id'] ?? null, 'Payment');
            $result = (string)($_POST['result'] ?? '');
            if (!in_array($result, ['confirmed', 'rejected'], true)) {
                throw new InvalidArgumentException('Choose confirm or reject.');
            }
            FinanceModel::verifyPayment(
                school_id(),
                $paymentId,
                $result,
                $this->optionalText($_POST['comments'] ?? '', 255) ?? '',
                Auth::user()
            );
            $this->respond(true, $result === 'confirmed' ? 'Payment verified and receipt issued.' : 'Payment rejected.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance');
        }
    }

    public function online(): void
    {
        $schoolId = school_id();
        $this->render('finance/online', [
            'pageTitle' => 'Online payment integration',
            'activeNav' => 'finance',
            'gateways' => FinanceModel::gateways($schoolId),
            'transactions' => FinanceModel::gatewayTransactions($schoolId),
            'invoices' => FinanceModel::invoices($schoolId),
            'students' => Student::listForSchool($schoolId, ['status' => 'active']),
        ]);
    }

    public function storeGateway(): void
    {
        try {
            $providerType = (string)($_POST['provider_type'] ?? '');
            $environment = (string)($_POST['environment'] ?? 'sandbox');
            if (!in_array($providerType, ['mobile_money', 'bank', 'card', 'other'], true)
                || !in_array($environment, ['sandbox', 'production'], true)) {
                throw new InvalidArgumentException('Choose valid provider type and environment.');
            }
            $code = strtoupper((string)$this->text($_POST['provider_code'] ?? '', 'Provider code', 40));
            if (!preg_match('/^[A-Z0-9_-]+$/', $code)) {
                throw new InvalidArgumentException('Provider code may contain only letters, numbers, underscores, and hyphens.');
            }
            $currency = strtoupper((string)($_POST['currency'] ?? 'UGX'));
            if (!preg_match('/^[A-Z]{3,10}$/', $currency)) {
                throw new InvalidArgumentException('Enter a valid currency code.');
            }
            FinanceModel::saveGateway(school_id(), [
                'provider_code' => $code,
                'provider_name' => $this->text($_POST['provider_name'] ?? '', 'Provider name', 100),
                'provider_type' => $providerType,
                'currency' => $currency,
                'environment' => $environment,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'notes' => $this->optionalText($_POST['notes'] ?? '', 255),
            ]);
            $this->respond(true, 'Gateway configuration saved. No provider connection was made.', 'finance_online');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance_online');
        }
    }

    public function toggleGateway(): void
    {
        try {
            $gatewayId = $this->positiveInt($_POST['gateway_id'] ?? null, 'Gateway');
            $active = (string)($_POST['active'] ?? '');
            if (!in_array($active, ['0', '1'], true)) {
                throw new InvalidArgumentException('Choose a valid gateway state.');
            }
            FinanceModel::setGatewayActive(school_id(), $gatewayId, $active === '1');
            $this->respond(true, 'Gateway state updated.', 'finance_online');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance_online');
        }
    }

    public function logGatewayTransaction(): void
    {
        try {
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['initiated', 'pending', 'failed'], true)) {
                throw new InvalidArgumentException('Only initiated, pending, or failed sandbox transactions can be entered here.');
            }
            $data = [
                'gateway_config_id' => $this->positiveInt($_POST['gateway_config_id'] ?? null, 'Gateway'),
                'provider_txn_id' => $this->optionalText($_POST['provider_txn_id'] ?? '', 120),
                'invoice_id' => $this->optionalInt($_POST['invoice_id'] ?? null),
                'student_id' => $this->optionalInt($_POST['student_id'] ?? null),
                'amount' => $this->amount($_POST['amount'] ?? null, 'Transaction amount'),
                'currency' => strtoupper((string)($_POST['currency'] ?? 'UGX')),
                'payer_msisdn' => $this->optionalText($_POST['payer_msisdn'] ?? '', 40),
                'payer_name' => $this->optionalText($_POST['payer_name'] ?? '', 160),
                'status' => $status,
                'failure_reason' => $this->optionalText($_POST['failure_reason'] ?? '', 255),
            ];
            if (!preg_match('/^[A-Z]{3,10}$/', $data['currency'])) {
                throw new InvalidArgumentException('Enter a valid currency code.');
            }
            FinanceModel::logGatewayTransaction(school_id(), $data);
            $this->respond(true, 'Sandbox transaction log created. No network request was made.', 'finance_online');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance_online');
        }
    }

    public function updateGatewayTransaction(): void
    {
        try {
            $transactionId = $this->positiveInt($_POST['transaction_id'] ?? null, 'Transaction');
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['initiated', 'pending', 'success', 'failed', 'reversed', 'duplicate', 'expired'], true)) {
                throw new InvalidArgumentException('Choose a valid gateway transaction status.');
            }
            FinanceModel::updateGatewayTransaction(
                school_id(),
                $transactionId,
                $status,
                $this->optionalText($_POST['resolution_note'] ?? '', 255) ?? '',
                Auth::user()
            );
            $this->respond(true, 'Gateway transaction monitoring status updated. It was not posted as a fee payment.', 'finance_online');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance_online');
        }
    }

    public function storeGrant(): void
    {
        try {
            $grantType = (string)($_POST['grant_type'] ?? '');
            if (!in_array($grantType, ['UPE', 'USE', 'other_grant'], true)) {
                throw new InvalidArgumentException('Choose a valid grant type.');
            }
            $expected = $this->amount($_POST['amount_expected'] ?? null, 'Expected amount', true);
            $received = $this->amount($_POST['amount_received'] ?? '0', 'Received amount', true);
            if ($received > $expected) {
                throw new InvalidArgumentException('Received amount cannot exceed the expected amount.');
            }
            $beneficiaryCount = $this->optionalInt($_POST['beneficiary_count'] ?? null);
            if ($beneficiaryCount !== null && $beneficiaryCount > 10000000) {
                throw new InvalidArgumentException('Beneficiary count is too large.');
            }
            FinanceModel::saveGrant(school_id(), [
                'grant_type' => $grantType,
                'academic_year_id' => $this->positiveInt($_POST['academic_year_id'] ?? null, 'Academic year'),
                'term_id' => $this->optionalInt($_POST['term_id'] ?? null),
                'amount_expected' => $expected,
                'amount_received' => $received,
                'beneficiary_count' => $beneficiaryCount,
                'per_learner_rate' => $this->optionalAmount($_POST['per_learner_rate'] ?? null, 'Per-learner rate'),
                'received_date' => $this->optionalDate($_POST['received_date'] ?? null, 'Received date'),
                'reference_number' => $this->optionalText($_POST['reference_number'] ?? '', 80),
                'notes' => $this->optionalText($_POST['notes'] ?? '', 255),
            ], Auth::user());
            $this->respond(true, 'Capitation grant recorded.', 'compliance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'compliance');
        }
    }

    public function recordGrantExpenditure(): void
    {
        try {
            $category = (string)($_POST['category'] ?? '');
            if (!in_array($category, ['instructional_materials', 'textbooks', 'school_feeds', 'development', 'staffing', 'utilities', 'other'], true)) {
                throw new InvalidArgumentException('Choose a valid expenditure category.');
            }
            FinanceModel::addGrantExpenditure(school_id(), [
                'grant_id' => $this->positiveInt($_POST['grant_id'] ?? null, 'Grant'),
                'category' => $category,
                'description' => $this->text($_POST['description'] ?? '', 'Description', 255),
                'amount' => $this->amount($_POST['amount'] ?? null, 'Expenditure amount'),
                'spent_date' => $this->date($_POST['spent_date'] ?? null, 'Spent date'),
                'beneficiary_summary' => $this->optionalText($_POST['beneficiary_summary'] ?? '', 255),
                'notes' => $this->optionalText($_POST['notes'] ?? '', 255),
            ], Auth::user());
            $this->respond(true, 'Grant expenditure recorded.', 'compliance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'compliance');
        }
    }

    public function storeEmisReturn(): void
    {
        try {
            $type = (string)($_POST['return_type'] ?? '');
            if (!in_array($type, ['EMIS_termly', 'EMIS_annual', 'census', 'inspection', 'other'], true)) {
                throw new InvalidArgumentException('Choose a valid return type.');
            }
            $data = [
                'academic_year_id' => $this->positiveInt($_POST['academic_year_id'] ?? null, 'Academic year'),
                'term_id' => $this->optionalInt($_POST['term_id'] ?? null),
                'return_type' => $type,
                'period_label' => $this->text($_POST['period_label'] ?? '', 'Period label', 60),
                'total_learners' => $this->nonNegativeInt($_POST['total_learners'] ?? null, 'Learner count'),
                'total_teachers' => $this->nonNegativeInt($_POST['total_teachers'] ?? null, 'Teacher count'),
                'total_support_staff' => $this->nonNegativeInt($_POST['total_support_staff'] ?? '0', 'Support staff count'),
                'remarks' => $this->optionalText($_POST['remarks'] ?? '', 255),
            ];
            FinanceModel::saveEmisReturn(school_id(), $data, Auth::user());
            $this->respond(true, 'Government return prepared as a draft.', 'compliance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'compliance');
        }
    }

    public function updateEmisStatus(): void
    {
        try {
            $returnId = $this->positiveInt($_POST['return_id'] ?? null, 'Return');
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['validated', 'submitted', 'accepted', 'rejected', 'returned_for_correction', 'draft'], true)) {
                throw new InvalidArgumentException('Choose a valid government-return status.');
            }
            FinanceModel::updateEmisStatus(
                school_id(),
                $returnId,
                $status,
                $this->optionalText($_POST['remarks'] ?? '', 255) ?? '',
                Auth::user()
            );
            $this->respond(true, 'Government return status updated.', 'compliance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'compliance');
        }
    }

    public function storeStructure(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $field = 'name';
        try {
            $classLevel = (string)($_POST['class_level'] ?? 'all');
            $category = (string)($_POST['student_category'] ?? 'all');
            $status = (string)($_POST['status'] ?? 'draft');
            if (!in_array($classLevel, ['all', 'O-Level', 'A-Level'], true)) {
                throw new InvalidArgumentException('Choose a valid class level.');
            }
            if (!in_array($category, ['all', 'day', 'boarding', 'new', 'continuing'], true)) {
                throw new InvalidArgumentException('Choose a valid student category.');
            }
            if (!in_array($status, ['draft', 'active'], true)) {
                throw new InvalidArgumentException('Choose a valid structure status.');
            }
            $field = 'academic_year_id';
            $yearId = $this->positiveInt($_POST['academic_year_id'] ?? null, 'Academic year');
            $field = 'class_id';
            $classId = $this->optionalInt($_POST['class_id'] ?? null);
            $field = 'name';
            $name = $this->text($_POST['name'] ?? '', 'Structure name', 140);
            $field = 'term_id';
            $termId = $this->optionalInt($_POST['term_id'] ?? null);
            $field = 'effective_from';
            $effectiveFrom = $this->optionalDate($_POST['effective_from'] ?? null, 'Effective from');
            $field = 'description';
            $description = $this->optionalText($_POST['description'] ?? '', 255);
            $data = [
                'name' => $name,
                'academic_year_id' => $yearId,
                'term_id' => $termId,
                'class_level' => $classLevel,
                'class_id' => $classId,
                'student_category' => $category,
                'effective_from' => $effectiveFrom,
                'status' => $status,
                'description' => $description,
            ];
            $field = 'academic_year_id';
            FinanceModel::saveStructure(school_id(), $data, Auth::user());
            $this->respond(true, 'Fee structure created.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function storeStructureItem(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $field = 'fee_structure_id';
        try {
            $structureId = $this->positiveInt($_POST['fee_structure_id'] ?? null, 'Fee structure');
            $appliesTo = (string)($_POST['applies_to'] ?? 'all');
            if (!in_array($appliesTo, ['all', 'day', 'boarding'], true)) {
                throw new InvalidArgumentException('Choose who the fee item applies to.');
            }
            $sort = filter_var($_POST['sort_order'] ?? '0', FILTER_VALIDATE_INT);
            if ($sort === false || $sort < 0 || $sort > 32000) {
                $field = 'sort_order';
                throw new InvalidArgumentException('Sort order must be a whole number between 0 and 32000.');
            }
            $field = 'item_name';
            $itemName = $this->text($_POST['item_name'] ?? '', 'Fee item name', 140);
            $field = 'item_code';
            $itemCode = $this->optionalText($_POST['item_code'] ?? '', 40);
            $field = 'amount';
            $amount = $this->amount($_POST['amount'] ?? null, 'Item amount');
            $data = [
                'fee_structure_id' => $structureId,
                'item_name' => $itemName,
                'item_code' => $itemCode,
                'amount' => $amount,
                'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
                'applies_to' => $appliesTo,
                'sort_order' => (int)$sort,
            ];
            $field = 'fee_structure_id';
            FinanceModel::addStructureItem(school_id(), $data);
            $this->respond(true, 'Fee item added to the structure.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function setStructureStatus(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $field = 'structure_id';
        try {
            $structureId = $this->positiveInt($_POST['structure_id'] ?? null, 'Fee structure');
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['draft', 'active', 'archived'], true)) {
                throw new InvalidArgumentException('Choose a valid fee structure status.');
            }
            FinanceModel::setStructureStatus(school_id(), $structureId, $status, Auth::user());
            $this->respond(true, 'Fee structure status updated.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function bulkInvoice(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $field = 'fee_structure_id';
        try {
            $structureId = $this->positiveInt($_POST['fee_structure_id'] ?? null, 'Fee structure');
            $field = 'class_id';
            $classId = $this->optionalInt($_POST['class_id'] ?? null);
            $field = 'issue_date';
            $issueDate = $this->date($_POST['issue_date'] ?? null, 'Issue date');
            $field = 'due_date';
            $dueDate = $this->optionalDate($_POST['due_date'] ?? null, 'Due date');
            $field = 'notes';
            $notes = $this->optionalText($_POST['notes'] ?? '', 255);
            $field = 'fee_structure_id';
            $result = FinanceModel::bulkInvoice(school_id(), [
                'fee_structure_id' => $structureId,
                'class_id' => $classId,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'notes' => $notes,
            ], Auth::user());
            $message = 'Bulk invoicing complete: ' . $result['created'] . ' invoice(s) created, '
                . $result['skipped'] . ' student(s) skipped.';
            if ($result['truncated']) {
                $message .= ' The run limit was reached; run it again to continue with the remaining students.';
            }
            $this->respond(true, $message, 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function saveClearance(): void
    {
        if (!$this->allowed('edit')) {
            return;
        }
        $field = 'student_id';
        try {
            $studentId = $this->positiveInt($_POST['student_id'] ?? null, 'Student');
            $field = 'academic_year_id';
            $yearId = $this->positiveInt($_POST['academic_year_id'] ?? null, 'Academic year');
            $field = 'term_id';
            $termId = $this->optionalInt($_POST['term_id'] ?? null);
            $type = (string)($_POST['clearance_type'] ?? '');
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($type, ['examination', 'report_card', 'end_of_term', 'transfer', 'graduation'], true)) {
                $field = 'clearance_type';
                throw new InvalidArgumentException('Choose a valid clearance type.');
            }
            if (!in_array($status, ['pending', 'cleared', 'blocked', 'exception_granted'], true)) {
                $field = 'status';
                throw new InvalidArgumentException('Choose a valid clearance status.');
            }
            $field = 'reason';
            $reason = $this->optionalText($_POST['reason'] ?? '', 255);
            $field = 'policy_note';
            $policyNote = $this->optionalText($_POST['policy_note'] ?? '', 255);
            $field = 'exception_reason';
            $exceptionReason = $this->optionalText($_POST['exception_reason'] ?? '', 255);
            $field = 'student_id';
            FinanceModel::saveClearance(school_id(), [
                'student_id' => $studentId,
                'academic_year_id' => $yearId,
                'term_id' => $termId,
                'clearance_type' => $type,
                'status' => $status,
                'reason' => $reason,
                'policy_note' => $policyNote,
                'exception_reason' => $exceptionReason,
            ], Auth::user());
            $this->respond(true, 'Fee clearance saved.', 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function approveClearance(): void
    {
        if (!$this->allowed('approve')) {
            return;
        }
        $field = 'clearance_id';
        try {
            $clearanceId = $this->positiveInt($_POST['clearance_id'] ?? null, 'Clearance');
            $decision = (string)($_POST['action'] ?? '');
            if (!in_array($decision, ['cleared', 'exception_granted'], true)) {
                $field = 'action';
                throw new InvalidArgumentException('Choose to clear the student or grant an exception.');
            }
            $field = 'exception_reason';
            $reason = $this->optionalText($_POST['exception_reason'] ?? '', 255) ?? '';
            $field = 'clearance_id';
            FinanceModel::approveClearance(school_id(), $clearanceId, $decision, $reason, Auth::user());
            $this->respond(
                true,
                $decision === 'cleared'
                    ? 'Student cleared for examination and report release.'
                    : 'Fee clearance exception granted.',
                'finance'
            );
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function sendReminder(): void
    {
        if (!$this->allowed('add')) {
            return;
        }
        $field = 'student_id';
        try {
            $studentId = $this->positiveInt($_POST['student_id'] ?? null, 'Student');
            $field = 'academic_year_id';
            $yearId = $this->positiveInt($_POST['academic_year_id'] ?? null, 'Academic year');
            $field = 'term_id';
            $termId = $this->optionalInt($_POST['term_id'] ?? null);
            $field = 'invoice_id';
            $invoiceId = $this->optionalInt($_POST['invoice_id'] ?? null);
            $channel = (string)($_POST['channel'] ?? 'sms');
            if (!in_array($channel, ['sms', 'email', 'portal', 'print'], true)) {
                $field = 'channel';
                throw new InvalidArgumentException('Choose a valid reminder channel.');
            }
            $field = 'message';
            $message = $this->optionalText($_POST['message'] ?? '', 500);
            $field = 'student_id';
            FinanceModel::sendReminder(school_id(), [
                'student_id' => $studentId,
                'academic_year_id' => $yearId,
                'term_id' => $termId,
                'invoice_id' => $invoiceId,
                'channel' => $channel,
                'message' => $message,
            ], Auth::user());
            $message = in_array($channel, ['sms', 'email'], true)
                ? 'Fee reminder sent via ' . strtoupper($channel) . '.'
                : 'Fee reminder recorded.';
            $this->respond(true, $message, 'finance');
        } catch (DomainException|InvalidArgumentException $error) {
            $this->respond(false, $error->getMessage(), 'finance', [], [$field => $error->getMessage()]);
        }
    }

    public function bankReconciliation(): void
    {
        if (!$this->allowed('finance', 'view')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $accounts = $db->query(
            'SELECT * FROM bank_accounts WHERE school_id = ? AND status = \'active\' ORDER BY bank_name, account_name',
            [$schoolId]
        )->fetchAll();
        $accountId = (int)($_GET['bank_account_id'] ?? ($accounts[0]['id'] ?? 0));
        $periodStart = trim((string)($_GET['period_start'] ?? ''));
        $periodEnd = trim((string)($_GET['period_end'] ?? ''));

        $reconciliations = $db->query(
            'SELECT br.*, ba.bank_name, ba.account_number, u.full_name AS reconciled_by_name
               FROM bank_reconciliations br
               JOIN bank_accounts ba ON ba.id = br.bank_account_id
               LEFT JOIN users u ON u.id = br.reconciled_by
              WHERE br.bank_account_id = ?
              ORDER BY br.period_end DESC, br.id DESC
              LIMIT 50',
            [$accountId > 0 ? $accountId : 0]
        )->fetchAll();

        $unmatchedTransactions = [];
        $unmatchedPayments = [];
        $unmatchedExpenses = [];
        if ($accountId > 0 && $periodStart !== '' && $periodEnd !== '') {
            $unmatchedTransactions = $db->query(
                'SELECT * FROM bank_transactions
                  WHERE bank_account_id = ? AND is_reconciled = 0
                    AND transaction_date BETWEEN ? AND ?
                  ORDER BY transaction_date DESC, id DESC',
                [$accountId, $periodStart, $periodEnd]
            )->fetchAll();
            $unmatchedPayments = $db->query(
                'SELECT p.*, s.admission_number, s.first_name, s.last_name
                   FROM payments p
                   JOIN students s ON s.id = p.student_id
                  WHERE p.school_id = ? AND p.channel = \'bank\'
                    AND p.payment_date BETWEEN ? AND ?
                    AND p.status = \'verified\'
                    AND NOT EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.payment_id = p.id)
                  ORDER BY p.payment_date DESC',
                [$schoolId, $periodStart, $periodEnd]
            )->fetchAll();
            $unmatchedExpenses = $db->query(
                'SELECT e.* FROM expenses e
                  WHERE e.school_id = ? AND e.payment_method = \'bank\'
                    AND e.expense_date BETWEEN ? AND ?
                    AND e.approval_status = \'approved\'
                    AND NOT EXISTS (SELECT 1 FROM bank_transactions bt WHERE bt.expense_id = e.id)
                  ORDER BY e.expense_date DESC',
                [$schoolId, $periodStart, $periodEnd]
            )->fetchAll();
        }

        $this->render('finance/bank_reconciliation', [
            'pageTitle' => 'Bank Reconciliation',
            'activeNav' => 'finance',
            'accounts' => $accounts,
            'accountId' => $accountId,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'reconciliations' => $reconciliations,
            'unmatchedTransactions' => $unmatchedTransactions,
            'unmatchedPayments' => $unmatchedPayments,
            'unmatchedExpenses' => $unmatchedExpenses,
        ]);
    }

    public function saveBankAccount(): void
    {
        if (!$this->allowed('finance', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();
        $id = (int)($_POST['id'] ?? 0);
        $bankName = trim((string)($_POST['bank_name'] ?? ''));
        $accountName = trim((string)($_POST['account_name'] ?? ''));
        $accountNumber = trim((string)($_POST['account_number'] ?? ''));
        $branchName = trim((string)($_POST['branch_name'] ?? ''));
        $swiftCode = trim((string)($_POST['swift_code'] ?? ''));
        $currency = (string)($_POST['currency'] ?? 'UGX');
        $openingBalance = $this->optionalAmount($_POST['opening_balance'] ?? null, 'Opening balance');
        $currentBalance = $this->optionalAmount($_POST['current_balance'] ?? null, 'Current balance');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $status = (string)($_POST['status'] ?? 'active');

        if ($bankName === '' || mb_strlen($bankName) > 100
            || $accountName === '' || mb_strlen($accountName) > 140
            || $accountNumber === '' || mb_strlen($accountNumber) > 40
            || mb_strlen($branchName) > 80 || mb_strlen($swiftCode) > 20
            || !in_array($currency, ['UGX', 'USD', 'EUR', 'KES', 'TZS'], true)
            || !in_array($status, ['active', 'closed', 'frozen'], true)
            || ($openingBalance !== null && $openingBalance < 0)
            || ($currentBalance !== null && $currentBalance < 0)) {
            $this->respond(false, 'Enter valid bank account details.', 'finance');
            return;
        }

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = $db->query(
                    'SELECT id FROM bank_accounts WHERE id = ? AND school_id = ?',
                    [$id, $schoolId]
                )->fetch();
                if (!$exists) {
                    throw new DomainException('Bank account not found.');
                }
                $db->prepare(
                    'UPDATE bank_accounts SET bank_name = ?, account_name = ?, account_number = ?,
                        branch_name = ?, swift_code = ?, currency = ?, opening_balance = ?,
                        current_balance = ?, status = ?
                      WHERE id = ? AND school_id = ?'
                )->execute([
                    $bankName, $accountName, $accountNumber, $branchName, $swiftCode, $currency,
                    $openingBalance ?? 0, $currentBalance ?? 0, $status, $id, $schoolId
                ]);
                if ($isDefault) {
                    $db->prepare(
                        'UPDATE bank_accounts SET is_default = 0 WHERE school_id = ? AND id <> ?'
                    )->execute([$schoolId, $id]);
                    $db->prepare(
                        'UPDATE bank_accounts SET is_default = 1 WHERE id = ? AND school_id = ?'
                    )->execute([$id, $schoolId]);
                }
                audit('finance', 'update_bank_account', 'bank_accounts', $id, 'Updated bank account ' . $accountNumber);
                $this->respond(true, 'Bank account updated.', 'finance');
            } else {
                if ($isDefault) {
                    $db->prepare('UPDATE bank_accounts SET is_default = 0 WHERE school_id = ?')->execute([$schoolId]);
                }
                $db->prepare(
                    'INSERT INTO bank_accounts
                        (school_id, bank_name, account_name, account_number, branch_name, swift_code,
                         currency, opening_balance, current_balance, is_default, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $schoolId, $bankName, $accountName, $accountNumber, $branchName, $swiftCode,
                    $currency, $openingBalance ?? 0, $currentBalance ?? 0, $isDefault, $status
                ]);
                $newId = (int)$db->lastInsertId();
                audit('finance', 'create_bank_account', 'bank_accounts', $newId, 'Created bank account ' . $accountNumber);
                $this->respond(true, 'Bank account created.', 'finance');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'finance');
        }
    }

    public function saveBankTransaction(): void
    {
        if (!$this->allowed('finance', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $bankAccountId = $this->positiveInt($_POST['bank_account_id'] ?? null, 'Bank account');
        $account = $db->query(
            'SELECT id, currency FROM bank_accounts WHERE id = ? AND school_id = ? AND status = \'active\'',
            [$bankAccountId, $schoolId]
        )->fetch();
        if (!$account) {
            $this->respond(false, 'Choose an active bank account.', 'finance');
            return;
        }

        $transactionDate = $this->date($_POST['transaction_date'] ?? null, 'Transaction date');
        $valueDate = $this->optionalDate($_POST['value_date'] ?? null, 'Value date');
        $description = $this->text($_POST['description'] ?? '', 'Description', 255);
        $deposit = $this->optionalAmount($_POST['deposit'] ?? null, 'Deposit');
        $withdrawal = $this->optionalAmount($_POST['withdrawal'] ?? null, 'Withdrawal');
        $reference = $this->optionalText($_POST['reference'] ?? '', 80);
        $source = (string)($_POST['source'] ?? 'manual');
        $paymentId = $this->optionalInt($_POST['payment_id'] ?? null);
        $expenseId = $this->optionalInt($_POST['expense_id'] ?? null);

        if ($deposit !== null && $withdrawal !== null) {
            $this->respond(false, 'Enter either a deposit or a withdrawal, not both.', 'finance');
            return;
        }
        if ($deposit === null && $withdrawal === null) {
            $this->respond(false, 'Enter a deposit or withdrawal amount.', 'finance');
            return;
        }
        if (!in_array($source, ['manual', 'import', 'system'], true)) {
            $this->respond(false, 'Invalid source.', 'finance');
            return;
        }

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO bank_transactions
                    (bank_account_id, transaction_date, value_date, description, deposit, withdrawal,
                     reference, source, payment_id, expense_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $bankAccountId, $transactionDate, $valueDate, $description,
                $deposit ?? 0, $withdrawal ?? 0, $reference, $source, $paymentId, $expenseId,
                (int)(Auth::user()['id'] ?? 0)
            ]);
            $newId = (int)$db->lastInsertId();
            audit('finance', 'create_bank_transaction', 'bank_transactions', $newId, 'Added bank transaction: ' . $description);
            $db->commit();
            $this->respond(true, 'Bank transaction recorded.', 'finance');
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'finance');
        }
    }

    public function reconcileBank(): void
    {
        if (!$this->allowed('finance', 'approve')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $bankAccountId = $this->positiveInt($_POST['bank_account_id'] ?? null, 'Bank account');
        $periodStart = $this->date($_POST['period_start'] ?? null, 'Period start');
        $periodEnd = $this->date($_POST['period_end'] ?? null, 'Period end');
        $statementBalance = $this->amount($_POST['statement_balance'] ?? null, 'Statement balance');
        $notes = $this->optionalText($_POST['notes'] ?? null, 1000);

        if ($periodEnd < $periodStart) {
            $this->respond(false, 'Period end must be on or after period start.', 'finance');
            return;
        }

        $db->beginTransaction();
        try {
            $existing = $db->query(
                'SELECT id FROM bank_reconciliations WHERE bank_account_id = ? AND period_start = ? AND period_end = ? AND status <> \'completed\'',
                [$bankAccountId, $periodStart, $periodEnd]
            )->fetch();
            if ($existing) {
                throw new DomainException('An unreconciled reconciliation already exists for this account and period.');
            }

            $account = $db->query('SELECT opening_balance, current_balance FROM bank_accounts WHERE id = ?', [$bankAccountId])->fetch();
            $bookBalance = (float)($account['current_balance'] ?? 0);

            $matchedCount = 0;
            $unmatchedCount = 0;

            $matchedTxns = $db->query(
                'SELECT id FROM bank_transactions
                  WHERE bank_account_id = ? AND is_reconciled = 1
                    AND transaction_date BETWEEN ? AND ?',
                [$bankAccountId, $periodStart, $periodEnd]
            )->fetchAll();
            $matchedCount = count($matchedTxns);

            $unmatchedTxns = $db->query(
                'SELECT id FROM bank_transactions
                  WHERE bank_account_id = ? AND is_reconciled = 0
                    AND transaction_date BETWEEN ? AND ?',
                [$bankAccountId, $periodStart, $periodEnd]
            )->fetchAll();
            $unmatchedCount = count($unmatchedTxns);

            $difference = round($statementBalance - $bookBalance, 2);

            $db->prepare(
                'INSERT INTO bank_reconciliations
                    (bank_account_id, period_start, period_end, statement_balance, book_balance,
                     difference, unmatched_count, reconciled_by, reconciled_at, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), \'completed\', ?)'
            )->execute([
                $bankAccountId, $periodStart, $periodEnd, $statementBalance, $bookBalance,
                $difference, $unmatchedCount, (int)(Auth::user()['id'] ?? 0), $notes
            ]);
            $reconId = (int)$db->lastInsertId();

            audit('finance', 'complete_bank_reconciliation', 'bank_reconciliations', $reconId,
                'Completed bank reconciliation for period ' . $periodStart . ' to ' . $periodEnd,
                null, ['statement_balance' => $statementBalance, 'book_balance' => $bookBalance, 'difference' => $difference]);

            $db->commit();
            $this->respond(true, 'Bank reconciliation completed. Difference: ' . number_format($difference, 2) . '.', 'finance');
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'finance');
        }
    }

    protected function allowed(string $module = 'finance', string $action = 'view'): bool
    {
        if (Auth::can('finance', $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this finance action.', 'dashboard');
        return false;
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return $parsed;
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 0 || $parsed > 10000000) {
            throw new InvalidArgumentException($label . ' must be a non-negative whole number.');
        }
        return $parsed;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || trim((string)$value) === '' || (string)$value === '0') {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1) {
            throw new InvalidArgumentException('Choose a valid linked record.');
        }
        return $parsed;
    }

    private function amount(mixed $value, string $label, bool $allowZero = false): float
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', (string)$value)) {
            throw new InvalidArgumentException($label . ' must be a valid amount with up to two decimals.');
        }
        $amount = (float)$value;
        if ((!$allowZero && $amount <= 0) || $amount > 999999999999.99) {
            throw new InvalidArgumentException($label . ' is outside the permitted range.');
        }
        return round($amount, 2);
    }

    private function optionalAmount(mixed $value, string $label): ?float
    {
        return $value === null || trim((string)$value) === '' ? null : $this->amount($value, $label, true);
    }

    private function date(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new InvalidArgumentException($label . ' must be a valid date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($label . ' must be a valid date.');
        }
        return $value;
    }

    private function optionalDate(mixed $value, string $label): ?string
    {
        return $value === null || trim((string)$value) === '' ? null : $this->date($value, $label);
    }

    private function text(mixed $value, string $label, int $maxLength): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        $text = trim($value);
        if ($text === '' || mb_strlen($text) > $maxLength) {
            throw new InvalidArgumentException($label . ' is required and must be at most ' . $maxLength . ' characters.');
        }
        return $text;
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid text value.');
        }
        $text = trim($value);
        if (mb_strlen($text) > $maxLength) {
            throw new InvalidArgumentException('Text value exceeds the allowed length.');
        }
        return $text === '' ? null : $text;
    }

    public function invoicePdf(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::conn();
        $inv = Database::query(
            'SELECT i.*, s.admission_number, s.first_name, s.last_name, c.name AS class_name
               FROM fee_invoices i
               JOIN students s ON s.id = i.student_id
               LEFT JOIN classes c ON c.id = s.current_class_id
              WHERE i.id = ? AND i.school_id = ?',
            [$id, school_id()]
        )->fetch();
        if (!$inv) {
            $this->respond(false, 'Invoice not found.', 'finance');
        }
        $items = Database::query(
            'SELECT item_name, amount FROM fee_invoice_items WHERE invoice_id = ? ORDER BY sort_order, id',
            [$id]
        );
        $school = school_row() ?? [];
        $html = PdfService::invoiceHtml($inv, $items, $school);
        PdfService::output($html, 'invoice_'.$inv['invoice_number'].'.pdf');
    }

    public function receiptPdf(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $db = Database::conn();
        $rec = Database::query(
            'SELECT r.*, s.admission_number, s.first_name, s.last_name,
                    p.payment_reference, p.channel, r.method_label, p.payer_name, p.payer_phone,
                    p.payment_date, p.received_by, p.external_reference,
                    u.full_name AS received_by_name,
                    y.name AS year_name, t.name AS term_name,
                    i.balance AS balance_after,
                    i.total_amount AS invoice_total
               FROM receipts r
               JOIN students s ON s.id = r.student_id
               LEFT JOIN payments p ON p.id = r.payment_id
               LEFT JOIN users u ON u.id = p.received_by
               LEFT JOIN fee_invoices i ON i.id = p.invoice_id
               LEFT JOIN academic_years y ON y.id = i.academic_year_id
               LEFT JOIN terms t ON t.id = i.term_id
              WHERE r.id = ? AND r.school_id = ?',
            [$id, school_id()]
        )->fetch();
        if (!$rec) {
            $this->respond(false, 'Receipt not found.', 'finance');
        }

        $school = school_row() ?? [];

        // Balance before = after - this receipt amount
        $balanceAfter = (float)($rec['balance_after'] ?? 0);
        $balanceBefore = $balanceAfter + (float)($rec['amount'] ?? 0);

        // Invoice items for breakdown
        $items = [];
        $invoiceId = (int)($rec['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $items = Database::query(
                'SELECT item_name, amount, discount_amount, net_amount, source
                   FROM fee_invoice_items
                  WHERE invoice_id = ?
                  ORDER BY sort_order, id',
                [$invoiceId]
            );
        } elseif ((int)($rec['payment_id'] ?? 0) > 0) {
            $payment = Database::query(
                'SELECT invoice_id FROM payments WHERE id = ? AND school_id = ?',
                [(int)$rec['payment_id'], school_id()]
            )->fetch();
            if ($payment && (int)$payment['invoice_id'] > 0) {
                $items = Database::query(
                    'SELECT item_name, amount, discount_amount, net_amount, source
                       FROM fee_invoice_items
                      WHERE invoice_id = ?
                      ORDER BY sort_order, id',
                    [(int)$payment['invoice_id']]
                );
                $invoiceId = (int)$payment['invoice_id'];
            }
        }

        // If no invoice items, show a single line for the receipt amount
        if (!$items) {
            $items = [[
                'item_name' => $rec['narrative'] ?? 'Fee payment',
                'amount' => $rec['amount'],
                'discount_amount' => 0,
                'net_amount' => $rec['amount'],
                'source' => 'manual',
            ]];
        }

        $meta = [
            'received_by_name' => $rec['received_by_name'] ?? '-',
            'year_name' => $rec['year_name'] ?? '-',
            'term_name' => $rec['term_name'] ?? '-',
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'delivery_status' => $rec['delivery_status'] ?? 'printed',
        ];

        $html = PdfService::receiptHtml($rec, $school, $items, $meta);
        PdfService::output($html, 'receipt_'.$rec['receipt_number'].'.pdf');
    }

    public function voidReceipt(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $reason   = trim((string)($_POST['reason'] ?? ''));
        $schoolId = school_id();

        if ($reason === '') {
            $this->respond(false, 'Enter a void reason.', 'finance', [], ['reason' => 'Reason is required.']);
            return;
        }

        $db = Database::conn();
        $rec = Database::query(
            'SELECT id, receipt_number, is_void FROM receipts WHERE id = ? AND school_id = ?',
            [$id, $schoolId]
        )->fetch();
        if (!$rec) {
            $this->respond(false, 'Receipt not found.', 'finance');
            return;
        }
        if ((int)$rec['is_void'] === 1) {
            $this->respond(false, 'Receipt is already voided.', 'finance');
            return;
        }

        $db->prepare(
            'UPDATE receipts SET is_void = 1, void_reason = ?, delivery_status = \'void\' WHERE id = ?'
        )->execute([$reason, $id]);

        audit('finance', 'receipt_voided', 'receipts', $id, 'Receipt voided: ' . $reason);
        $this->respond(true, 'Receipt voided.', 'finance');
    }

    public function reprintReceipt(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        $db = Database::conn();
        $rec = Database::query(
            'SELECT receipt_number, printed_count FROM receipts WHERE id = ? AND school_id = ?',
            [$id, $schoolId]
        )->fetch();
        if (!$rec) {
            $this->respond(false, 'Receipt not found.', 'finance');
            return;
        }

        $db->prepare(
            'UPDATE receipts SET printed_count = printed_count + 1, delivery_status = \'printed\' WHERE id = ?'
        )->execute([$id]);

        audit('finance', 'receipt_reprinted', 'receipts', $id, 'Receipt reprinted.');
        $this->respond(true, 'Receipt reprinted.', 'finance');
    }

    public function emailReceipt(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        $db = Database::conn();
        $rec = Database::query(
            'SELECT r.*, s.admission_number, s.first_name, s.last_name,
                    g.email AS guardian_email, g.phone AS guardian_phone
               FROM receipts r
               JOIN students s ON s.id = r.student_id
               LEFT JOIN student_guardians sg ON sg.student_id = s.id AND sg.is_primary = 1
               LEFT JOIN guardians g ON g.id = sg.guardian_id
              WHERE r.id = ? AND r.school_id = ?',
            [$id, $schoolId]
        )->fetch();
        if (!$rec) {
            $this->respond(false, 'Receipt not found.', 'finance');
            return;
        }

        $to = $rec['guardian_email'] ?? '';
        if ($to === '') {
            $this->respond(false, 'No guardian email on file.', 'finance');
            return;
        }

        $school = school_row() ?? [];
        $html = PdfService::receiptHtml($rec, $school);
        $subject = 'Payment Receipt - ' . e($rec['receipt_number']);
        $body = '<p>Dear Guardian,</p><p>Please find attached the payment receipt for <strong>' . e($rec['first_name'] . ' ' . $rec['last_name']) . '</strong> (Admission No: ' . e($rec['admission_number']) . ').</p><p>Amount: ' . number_format((float)$rec['amount'], 2) . ' ' . e($school['currency_symbol'] ?? 'Ugx') . '</p><p>Receipt No: ' . e($rec['receipt_number']) . '</p><p>Thank you.</p>';

        $sent = MailService::send($to, $subject, $body);
        if ($sent) {
            $db->prepare('UPDATE receipts SET delivery_status = \'emailed\' WHERE id = ?')->execute([$id]);
            audit('finance', 'receipt_emailed', 'receipts', $id, 'Receipt emailed to guardian.');
            $this->respond(true, 'Receipt emailed.', 'finance');
        } else {
            $this->respond(false, 'Failed to send email.', 'finance');
        }
    }

    public function smsReceipt(): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        $db = Database::conn();
        $rec = Database::query(
            'SELECT r.*, s.admission_number, s.first_name, s.last_name,
                    g.phone AS guardian_phone
               FROM receipts r
               JOIN students s ON s.id = r.student_id
               LEFT JOIN student_guardians sg ON sg.student_id = s.id AND sg.is_primary = 1
               LEFT JOIN guardians g ON g.id = sg.guardian_id
              WHERE r.id = ? AND r.school_id = ?',
            [$id, $schoolId]
        )->fetch();
        if (!$rec) {
            $this->respond(false, 'Receipt not found.', 'finance');
            return;
        }

        $to = $rec['guardian_phone'] ?? '';
        if ($to === '') {
            $this->respond(false, 'No guardian phone on file.', 'finance');
            return;
        }

        $message = 'SSMS Receipt ' . e($rec['receipt_number']) . ': ' . number_format((float)$rec['amount'], 2) . ' ' . e($school['currency_symbol'] ?? 'Ugx') . ' received for ' . e($rec['first_name'] . ' ' . $rec['last_name']) . ' (' . e($rec['admission_number']) . '). Thank you.';

        $result = SmsService::send($to, $message);
        if ($result['ok']) {
            $db->prepare('UPDATE receipts SET delivery_status = \'sms\' WHERE id = ?')->execute([$id]);
            audit('finance', 'receipt_sms_sent', 'receipts', $id, 'Receipt SMS sent to guardian.');
            $this->respond(true, 'Receipt sent via SMS.', 'finance');
        } else {
            $this->respond(false, 'SMS failed: ' . $result['error'], 'finance');
        }
    }

    public function receipts(): void
    {
        $schoolId = school_id();
        $db = Database::conn();
        $receipts = Database::query(
            'SELECT r.*, s.admission_number, s.first_name, s.last_name,
                    p.channel, p.payment_date, u.full_name AS received_by_name
               FROM receipts r
               JOIN students s ON s.id = r.student_id
               LEFT JOIN payments p ON p.id = r.payment_id
               LEFT JOIN users u ON u.id = p.received_by
              WHERE r.school_id = ?
              ORDER BY r.issued_at DESC, r.id DESC
              LIMIT 200',
            [$schoolId]
        );

        $this->render('finance/receipts/index', [
            'pageTitle'   => 'Receipts',
            'activeNav'   => 'finance',
            'receipts'    => $receipts,
        ]);
    }

    public function exportCsv(): void
    {
        ImportExportService::financeExport(school_id());
    }

    public function feeClearanceCertificate(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $yearId = (int)($_GET['year_id'] ?? 0);
        $termId = (int)($_GET['term_id'] ?? 0);
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
            $this->respond(false, 'Student not found.', 'finance');
            return;
        }
        $balances = Database::query(
            'SELECT i.total_amount, i.paid_amount, i.balance,
                    y.name AS year_name, t.name AS term_name
               FROM fee_invoices i
               JOIN academic_years y ON y.id = i.academic_year_id
               LEFT JOIN terms t ON t.id = i.term_id
              WHERE i.student_id = ? AND i.school_id = ?
                AND (? = 0 OR i.academic_year_id = ?)
                AND (? = 0 OR i.term_id = ?)
              ORDER BY i.created_at DESC',
            [$studentId, $schoolId, $yearId, $yearId, $termId, $termId]
        );
        $totalInvoiced = 0.0; $totalPaid = 0.0; $balance = 0.0; $yearName = '-'; $termName = '-';
        foreach ($balances as $b) {
            $totalInvoiced += (float)$b['total_amount'];
            $totalPaid += (float)$b['paid_amount'];
            $balance += (float)$b['balance'];
            $yearName = $b['year_name'] ?? '-';
            $termName = $b['term_name'] ?? '-';
        }
        $school = school_row() ?? [];
        $html = PdfService::feeClearanceCertificateHtml($student, $school, [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'academic_year' => $yearName,
            'term' => $termName,
        ]);
        PdfService::output($html, 'fee_clearance_'.$student['admission_number'].'.pdf');
    }

    public function paymentReminder(): void
    {
        $schoolId = school_id();
        $studentId = (int)($_GET['student_id'] ?? 0);
        $yearId = (int)($_GET['year_id'] ?? 0);
        $termId = (int)($_GET['term_id'] ?? 0);
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
            $this->respond(false, 'Student not found.', 'finance');
            return;
        }
        $balances = Database::query(
            'SELECT i.total_amount, i.paid_amount, i.balance, i.due_date,
                    y.name AS year_name, t.name AS term_name
               FROM fee_invoices i
               JOIN academic_years y ON y.id = i.academic_year_id
               LEFT JOIN terms t ON t.id = i.term_id
              WHERE i.student_id = ? AND i.school_id = ? AND i.balance > 0
                AND (? = 0 OR i.academic_year_id = ?)
                AND (? = 0 OR i.term_id = ?)
              ORDER BY i.created_at DESC',
            [$studentId, $schoolId, $yearId, $yearId, $termId, $termId]
        );
        $totalInvoiced = 0.0; $totalPaid = 0.0; $balance = 0.0; $dueDate = '-'; $yearName = '-'; $termName = '-';
        foreach ($balances as $b) {
            $totalInvoiced += (float)$b['total_amount'];
            $totalPaid += (float)$b['paid_amount'];
            $balance += (float)$b['balance'];
            $dueDate = $b['due_date'] ?? '-';
            $yearName = $b['year_name'] ?? '-';
            $termName = $b['term_name'] ?? '-';
        }
        $school = school_row() ?? [];
        $html = PdfService::paymentReminderHtml($school, $student, [
            'balance' => $balance,
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'due_date' => $dueDate,
            'academic_year' => $yearName,
            'term' => $termName,
        ]);
        PdfService::output($html, 'fee_reminder_'.$student['admission_number'].'.pdf');
    }
}
