<?php /* Fees & payments: invoicing, bulk invoices, fee structures, payments, receipts, clearance and exports. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Finance &amp; fees</h1>
    <p class="text-muted small mb-0">Create student invoices, record payments, verify funds and issue receipts.</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?= url('finance_online') ?>">Online payments</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= url('compliance') ?>">Compliance &amp; grants</a>
    <?php if ($canAdd || $canEdit): ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('finance/export-csv') ?>"><i class="bi bi-file-earmark-excel"></i> CSV</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">Create fee invoice</div>
      <div class="card-body">
<?php if (!$years || !$students): ?>
        <div class="alert alert-warning mb-0">An academic year and active students are required before invoicing.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/store-invoice') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="invoice_student">Student</label>
            <select class="form-select" id="invoice_student" name="student_id" required>
              <option value="">Choose…</option>
<?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" for="invoice_year">Academic year</label>
              <select class="form-select" id="invoice_year" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['id'] ?>" <?= !empty($year['is_active']) ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="invoice_term">Term (optional)</label>
              <select class="form-select" id="invoice_term" name="term_id">
                <option value="">No term</option>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="invoice_item">Fee item</label>
            <input class="form-control" id="invoice_item" name="item_name" maxlength="140" required placeholder="e.g. Term tuition">
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label" for="invoice_amount">Amount (UGX)</label>
              <input class="form-control" id="invoice_amount" name="amount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="invoice_issue">Issue date</label>
              <input class="form-control" id="invoice_issue" name="issue_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="invoice_due">Due date</label>
              <input class="form-control" id="invoice_due" name="due_date" type="date">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="invoice_notes">Notes</label>
            <input class="form-control" id="invoice_notes" name="notes" maxlength="255">
          </div>
          <button class="btn btn-primary" type="submit">Create invoice</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header">Record a payment</div>
      <div class="card-body">
<?php $openInvoices = array_filter($invoices, static fn (array $invoice): bool => in_array($invoice['status'], ['unpaid', 'part_paid'], true) && (float)$invoice['balance'] > 0); ?>
<?php if (!$openInvoices): ?>
        <div class="alert alert-secondary mb-0">There are no unpaid invoices to receive a payment against.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/record-payment') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="payment_invoice">Invoice</label>
            <select class="form-select" id="payment_invoice" name="invoice_id" required>
<?php foreach ($openInvoices as $invoice): ?>
              <option value="<?= (int)$invoice['id'] ?>"><?= e($invoice['invoice_number'] . ' — ' . $invoice['last_name'] . ' ' . $invoice['first_name'] . ' — UGX ' . number_format((float)$invoice['balance'], 2)) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label" for="payment_amount">Amount (UGX)</label>
              <input class="form-control" id="payment_amount" name="amount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="payment_date">Payment date</label>
              <input class="form-control" id="payment_date" name="payment_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="payment_method">Method</label>
              <select class="form-select" id="payment_method" name="payment_method_id">
                <option value="">Use channel below</option>
<?php foreach ($methods as $method): ?>
                <option value="<?= (int)$method['id'] ?>"><?= e($method['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label" for="payment_channel">Channel if no method selected</label>
              <select class="form-select" id="payment_channel" name="channel">
<?php foreach (['cash', 'bank', 'cheque', 'mobile_money', 'online', 'other'] as $channel): ?>
                <option value="<?= e($channel) ?>"><?= e(ucwords(str_replace('_', ' ', $channel))) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="payer_name">Payer name</label>
              <input class="form-control" id="payer_name" name="payer_name" maxlength="160">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="payer_phone">Payer phone</label>
              <input class="form-control" id="payer_phone" name="payer_phone" maxlength="40">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" for="external_reference">Bank/mobile reference</label>
              <input class="form-control" id="external_reference" name="external_reference" maxlength="80">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="payment_notes">Notes</label>
              <input class="form-control" id="payment_notes" name="notes" maxlength="255">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Record pending payment</button>
          <span class="text-muted small ms-2">Payment is applied and receipted only after verification.</span>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card app-card mb-4">
  <div class="card-header">Fee invoices</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Invoice</th><th>Student</th><th>Year / term</th><th>Issued</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th style="width:12%"></th></tr></thead>
      <tbody>
<?php if (!$invoices): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No fee invoices have been created.</td></tr>
<?php else: foreach ($invoices as $invoice): ?>
        <tr>
          <td><?= e($invoice['invoice_number']) ?></td>
          <td><?= e($invoice['admission_number'] . ' — ' . $invoice['last_name'] . ' ' . $invoice['first_name']) ?></td>
          <td><?= e($invoice['year_name'] . ($invoice['term_name'] ? ' / ' . $invoice['term_name'] : '')) ?></td>
          <td><?= e($invoice['issue_date']) ?></td>
          <td>UGX <?= number_format((float)$invoice['total_amount'], 2) ?></td>
          <td>UGX <?= number_format((float)$invoice['paid_amount'], 2) ?></td>
          <td>UGX <?= number_format((float)$invoice['balance'], 2) ?></td>
          <td><span class="badge text-bg-<?= $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'part_paid' ? 'warning' : 'secondary') ?>"><?= e(ucwords(str_replace('_', ' ', $invoice['status']))) ?></span></td>
          <td class="text-nowrap">
            <a class="btn btn-outline-primary btn-sm" href="<?= url('finance/invoice-pdf', ['id' => (int)$invoice['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
          </td>
        </tr>
<?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card app-card">
  <div class="card-header">Payments, verification &amp; receipts</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Reference / date</th><th>Student / invoice</th><th>Method</th><th>Amount</th><th>Status / receipt</th><th>Verification</th></tr></thead>
      <tbody>
<?php if (!$payments): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No payments have been recorded.</td></tr>
<?php else: foreach ($payments as $payment): ?>
        <tr>
          <td><?= e($payment['payment_reference']) ?><div class="small text-muted"><?= e($payment['payment_date']) ?><?= $payment['external_reference'] ? ' · ' . e($payment['external_reference']) : '' ?></div></td>
          <td><?= e($payment['admission_number'] . ' — ' . $payment['last_name'] . ' ' . $payment['first_name']) ?><div class="small text-muted"><?= e($payment['invoice_number'] ?? 'No invoice') ?></div></td>
          <td><?= e(ucwords(str_replace('_', ' ', $payment['channel']))) ?></td>
          <td>UGX <?= number_format((float)$payment['amount_local'], 2) ?></td>
          <td><span class="badge text-bg-<?= $payment['status'] === 'verified' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e(ucwords($payment['status'])) ?></span>
<?php if ($payment['receipt_number']): ?><div class="small text-muted">Receipt <?= e($payment['receipt_number']) ?></div><?php endif; ?>
          </td>
          <td>
<?php if ($payment['status'] === 'pending'): ?>
            <form method="post" action="<?= url('finance/verify-payment') ?>" class="d-flex flex-wrap gap-1">
              <?= csrf_field() ?>
              <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
              <input class="form-control form-control-sm" name="comments" maxlength="255" placeholder="Evidence / note" aria-label="Verification comment">
              <button class="btn btn-success btn-sm" name="result" value="confirmed" type="submit">Confirm &amp; receipt</button>
              <button class="btn btn-outline-danger btn-sm" name="result" value="rejected" type="submit">Reject</button>
            </form>
<?php else: ?>
            <span class="text-muted small">—</span>
<?php endif; ?>
          </td>
        </tr>
<?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$itemStructures = array_filter($structures, static fn (array $s): bool => $s['status'] !== 'archived');
$activeStructures = array_filter($structures, static fn (array $s): bool => $s['status'] === 'active');
$reminderInvoices = array_filter(
    $invoices,
    static fn (array $i): bool => in_array($i['status'], ['unpaid', 'part_paid'], true) && (float)$i['balance'] > 0
);
$balanceTotals = ['invoiced' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
foreach ($balances as $balanceRow) {
    $balanceTotals['invoiced'] += (float)$balanceRow['total_invoiced'];
    $balanceTotals['paid'] += (float)$balanceRow['total_paid'];
    $balanceTotals['balance'] += (float)$balanceRow['balance'];
}
$reportParams = ['tab' => 'finance'];
if ($balanceYearId !== null) {
    $reportParams['year_id'] = $balanceYearId;
}
?>

<!-- ============================================================ Fee structures -->
<div class="row g-3 mt-1">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-cash-stack me-2"></i> New fee structure</div>
      <div class="card-body">
<?php if (!$years): ?>
        <div class="alert alert-warning mb-0">Create an academic year before defining fee structures.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/structures/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-journal-text"></i> Structure details</div>
          <div class="mb-2">
            <label class="form-label"><i class="bi bi-tag me-2"></i> Structure name <span class="text-danger">*</span></label>
            <div class="field-input">
              <input class="form-control has-icon-left" name="name" maxlength="140" required placeholder="e.g. Term 1 tuition 2026">
              <span class="input-icon bi bi-tag"></span>
            </div>
            <span class="invalid-feedback d-block" data-error-for="name"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label"><i class="bi bi-calendar me-2"></i> Academic year <span class="text-danger">*</span></label>
              <select class="form-select" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['id'] ?>" <?= !empty($year['is_active']) ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="academic_year_id"></span>
            </div>
            <div class="col">
              <label class="form-label"><i class="bi bi-calendar-week me-2"></i> Term</label>
              <select class="form-select" name="term_id">
                <option value="">No term</option>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="term_id"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label"><i class="bi bi-text-center me-2"></i> Class level</label>
              <select class="form-select" name="class_level">
                <option value="all">All levels</option>
                <option value="O-Level">O-Level</option>
                <option value="A-Level">A-Level</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label"><i class="bi bi-diagram-3 me-2"></i> Class (optional)</label>
              <select class="form-select" name="class_id">
                <option value="">Whole level / all</option>
<?php foreach ($classes as $class): ?>
                <option value="<?= (int)$class['id'] ?>"><?= e($class['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="class_id"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label"><i class="bi bi-person-badge me-2"></i> Student category</label>
              <select class="form-select" name="student_category">
                <option value="all">All students</option>
                <option value="day">Day students</option>
                <option value="boarding">Boarding students</option>
                <option value="new">New students</option>
                <option value="continuing">Continuing students</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label"><i class="bi bi-calendar-event me-2"></i> Effective from</label>
              <div class="field-input">
                <input class="form-control has-icon-left" type="date" name="effective_from">
                <span class="input-icon bi bi-calendar-event"></span>
              </div>
              <span class="invalid-feedback d-block" data-error-for="effective_from"></span>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label"><i class="bi bi-chat-text me-2"></i> Description</label>
            <input class="form-control" name="description" maxlength="255">
            <span class="invalid-feedback d-block" data-error-for="description"></span>
          </div>
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-gear me-2"></i> Status</label>
            <select class="form-select" name="status">
              <option value="draft">Draft</option>
              <option value="active">Active</option>
            </select>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-building me-2"></i> Create structure</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-lg-8' : 'col-12' ?>">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-table me-2"></i> Fee structures <span class="badge text-bg-light"><?= count($structures) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Structure</th><th>Year / term</th><th>Scope</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
          <tbody>
<?php if (!$structures): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No fee structures have been defined yet.</td></tr>
<?php else: foreach ($structures as $structure): ?>
            <tr>
              <td><strong><?= e($structure['name']) ?></strong><?php if ($structure['description']): ?><div class="small text-muted"><?= e($structure['description']) ?></div><?php endif; ?></td>
              <td class="small"><?= e($structure['year_name'] . ($structure['term_name'] ? ' / ' . $structure['term_name'] : '')) ?></td>
              <td class="small"><?= e($structure['class_name'] ?? ($structure['class_level'] === 'all' ? 'All classes' : $structure['class_level'])) ?>
                <div class="text-muted"><?= e($structure['student_category'] === 'all' ? 'All students' : ucfirst($structure['student_category']) . ' students') ?></div></td>
              <td><?= (int)$structure['item_count'] ?></td>
              <td data-order="<?= (float)$structure['total_amount'] ?>">UGX <?= number_format((float)$structure['total_amount'], 2) ?></td>
              <td><span class="badge text-bg-<?= $structure['status'] === 'active' ? 'success' : ($structure['status'] === 'archived' ? 'secondary' : 'warning') ?>"><?= e(ucfirst($structure['status'])) ?></span></td>
              <td class="text-nowrap">
<?php if ($canEdit): ?>
<?php if ($structure['status'] !== 'active' && (int)$structure['item_count'] > 0): ?>
                <form method="post" action="<?= url('finance/structures/status') ?>" data-ajax class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="structure_id" value="<?= (int)$structure['id'] ?>">
                  <input type="hidden" name="status" value="active">
                  <button class="btn btn-sm btn-success" type="submit">Activate</button>
                </form>
<?php endif; ?>
<?php if ($structure['status'] !== 'archived'): ?>
                <form method="post" action="<?= url('finance/structures/status') ?>" data-ajax class="d-inline" data-confirm="Archive this fee structure? Existing invoices are not affected.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="structure_id" value="<?= (int)$structure['id'] ?>">
                  <input type="hidden" name="status" value="archived">
                  <button class="btn btn-sm btn-outline-secondary" type="submit">Archive</button>
                </form>
<?php endif; ?>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ Fee structure items -->
<div class="row g-3 mt-1">
<?php if ($canEdit): ?>
  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header">Add fee item</div>
      <div class="card-body">
<?php if (!$itemStructures): ?>
        <div class="alert alert-secondary mb-0">Create a fee structure first, then add its fee items here.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/structures/items/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-cash-coin me-2"></i> Fee item</div>
          <div class="mb-2">
            <label class="form-label"><i class="bi bi-building me-2"></i> Fee structure <span class="text-danger">*</span></label>
            <select class="form-select" name="fee_structure_id" required>
              <option value="">Choose structure&hellip;</option>
<?php foreach ($itemStructures as $structure): ?>
              <option value="<?= (int)$structure['id'] ?>"><?= e($structure['name'] . ' - ' . $structure['year_name']) ?></option>
<?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="fee_structure_id"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label"><i class="bi bi-tag me-2"></i> Item name <span class="text-danger">*</span></label>
              <input class="form-control" name="item_name" maxlength="140" required placeholder="e.g. Tuition, Boarding, Uniform">
              <span class="invalid-feedback d-block" data-error-for="item_name"></span>
            </div>
            <div class="col-4">
              <label class="form-label"><i class="bi bi-barcode me-2"></i> Item code</label>
              <input class="form-control" name="item_code" maxlength="40" placeholder="TUITION">
              <span class="invalid-feedback d-block" data-error-for="item_code"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label"><i class="bi bi-currency-ugx me-2"></i> Amount (UGX) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">UGX</span>
                <input class="form-control" name="amount" type="number" min="0.01" step="0.01" required>
              </div>
              <span class="invalid-feedback d-block" data-error-for="amount"></span>
            </div>
            <div class="col">
              <label class="form-label"><i class="bi bi-people me-2"></i> Applies to</label>
              <select class="form-select" name="applies_to">
                <option value="all">All students</option>
                <option value="day">Day students</option>
                <option value="boarding">Boarding students</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label"><i class="bi bi-sort-numeric-down me-2"></i> Sort order</label>
              <input class="form-control" name="sort_order" type="number" min="0" max="32000" value="0">
              <span class="invalid-feedback d-block" data-error-for="sort_order"></span>
            </div>
            <div class="col d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_mandatory" id="item_mandatory" checked>
                <label class="form-check-label" for="item_mandatory"><i class="bi bi-exclamation-circle me-1"></i> Mandatory fee</label>
              </div>
            </div>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle me-2"></i> Add fee item</button>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canEdit ? 'col-lg-8' : 'col-12' ?>">
    <div class="card app-card h-100">
      <div class="card-header"><i class="bi bi-table me-2"></i> Fee structure items <span class="badge text-bg-light"><?= count($structureItems) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Structure</th><th>Fee item</th><th>Applies to</th><th>Mandatory</th><th>Amount</th></tr></thead>
          <tbody>
<?php if (!$structureItems): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No fee items have been added yet.</td></tr>
<?php else: foreach ($structureItems as $item): ?>
            <tr>
              <td class="small"><?= e($item['structure_name']) ?><?php if ($item['structure_status'] === 'archived'): ?><span class="badge text-bg-secondary ms-1">archived</span><?php endif; ?></td>
              <td><strong><?= e($item['item_name']) ?></strong><?php if ($item['item_code']): ?><div class="small text-muted"><?= e($item['item_code']) ?></div><?php endif; ?></td>
              <td class="small"><?= e($item['applies_to'] === 'all' ? 'All students' : ucfirst($item['applies_to'])) ?></td>
              <td><?= (int)$item['is_mandatory'] ? '<span class="badge text-bg-info">Yes</span>' : '<span class="badge text-bg-light">No</span>' ?></td>
              <td data-order="<?= (float)$item['amount'] ?>">UGX <?= number_format((float)$item['amount'], 2) ?></td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ Bulk invoicing + fee balances -->
<div class="row g-3 mt-1">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header">Bulk invoicing from a structure</div>
      <div class="card-body">
<?php if (!$activeStructures || !$years): ?>
        <div class="alert alert-secondary mb-0">Activate a fee structure with at least one fee item to bulk-generate invoices for a class or term.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/structures/bulk-invoice') ?>" data-ajax data-confirm="Generate invoices for every matching student who does not yet have one for this structure?">
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-file-earmark-invoice me-2"></i> Invoice settings</div>
          <div class="mb-2">
            <label class="form-label"><i class="bi bi-building me-2"></i> Fee structure <span class="text-danger">*</span></label>
            <select class="form-select" name="fee_structure_id" required>
              <option value="">Choose structure&hellip;</option>
<?php foreach ($activeStructures as $structure): ?>
              <option value="<?= (int)$structure['id'] ?>"><?= e($structure['name'] . ' - ' . $structure['year_name'] . ' - UGX ' . number_format((float)$structure['total_amount'], 2)) ?></option>
<?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="fee_structure_id"></span>
          </div>
          <div class="mb-2">
            <label class="form-label"><i class="bi bi-diagram-3 me-2"></i> Class</label>
            <select class="form-select" name="class_id">
              <option value="">Use the structure scope (level / all classes)</option>
<?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class['id'] ?>"><?= e($class['name'] . ' - ' . $class['level']) ?></option>
<?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="class_id"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label"><i class="bi bi-calendar-event me-2"></i> Issue date <span class="text-danger">*</span></label>
              <div class="field-input">
                <input class="form-control has-icon-left" type="date" name="issue_date" value="<?= e(date('Y-m-d')) ?>" required>
                <span class="input-icon bi bi-calendar-event"></span>
              </div>
              <span class="invalid-feedback d-block" data-error-for="issue_date"></span>
            </div>
            <div class="col">
              <label class="form-label"><i class="bi bi-calendar-date me-2"></i> Due date</label>
              <div class="field-input">
                <input class="form-control has-icon-left" type="date" name="due_date">
                <span class="input-icon bi bi-calendar-date"></span>
              </div>
              <span class="invalid-feedback d-block" data-error-for="due_date"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-chat-text me-2"></i> Invoice note</label>
            <input class="form-control" name="notes" maxlength="255">
            <span class="invalid-feedback d-block" data-error-for="notes"></span>
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-file-earmark-invoice me-2"></i> Generate invoices</button>
          <div class="form-text">Students already invoiced from this structure are skipped, so it is safe to run again.</div>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-lg-8' : 'col-12' ?>">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Fee balances &amp; debt management</span>
        <a class="btn btn-outline-primary btn-sm" href="<?= url('reports', $reportParams) ?>">Full debtor report</a>
      </div>
      <div class="card-body border-bottom py-2">
        <form method="get" action="<?= url('finance') ?>" class="d-flex flex-wrap gap-3 align-items-end">
          <div>
            <label class="form-label small mb-1" for="balance_year">Academic year</label>
            <select class="form-select form-select-sm" id="balance_year" name="year_id">
              <option value="">All years</option>
<?php foreach ($years as $year): ?>
              <option value="<?= (int)$year['id'] ?>" <?= $balanceYearId === (int)$year['id'] ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="show_all" value="1" id="balance_show_all" <?= $balanceIncludeAll ? 'checked' : '' ?>>
            <label class="form-check-label" for="balance_show_all">Show all students, not only debtors</label>
          </div>
          <button class="btn btn-outline-primary btn-sm" type="submit">Apply</button>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table data-order='[[ 5, "desc" ]]'>
          <thead><tr><th>Student</th><th>Class</th><th>Invoices</th><th>Invoiced</th><th>Paid</th><th>Balance</th><th>Overdue</th><th class="text-end">Documents</th></tr></thead>
          <tbody>
<?php if (!$balances): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No fee balances match this filter.</td></tr>
<?php else: foreach ($balances as $row): ?>
            <tr>
              <td><?= e($row['last_name'] . ' ' . $row['first_name']) ?><div class="small text-muted"><?= e($row['admission_number']) ?></div></td>
              <td><?= $row['class_name'] !== null ? e($row['class_name']) : '&mdash;' ?></td>
              <td><?= (int)$row['invoice_count'] ?></td>
              <td data-order="<?= (float)$row['total_invoiced'] ?>">UGX <?= number_format((float)$row['total_invoiced'], 2) ?></td>
              <td data-order="<?= (float)$row['total_paid'] ?>">UGX <?= number_format((float)$row['total_paid'], 2) ?></td>
              <td data-order="<?= (float)$row['balance'] ?>"><strong>UGX <?= number_format((float)$row['balance'], 2) ?></strong></td>
              <td><?= $row['days_overdue'] !== null && $row['days_overdue'] !== ''
                  ? '<span class="badge text-bg-danger">' . (int)$row['days_overdue'] . ' days overdue</span>'
                  : '<span class="text-muted small">&mdash;</span>' ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-success" href="<?= url('finance/clearance-certificate', ['student_id' => (int)$row['student_id'], 'year_id' => $balanceYearId ?? 0]) ?>" target="_blank"><i class="bi bi-file-earmark-check"></i> Clearance</a>
                <?php if ((float)$row['balance'] > 0): ?>
                <a class="btn btn-sm btn-outline-danger" href="<?= url('finance/payment-reminder', ['student_id' => (int)$row['student_id'], 'year_id' => $balanceYearId ?? 0]) ?>" target="_blank"><i class="bi bi-envelope"></i> Reminder</a>
                <?php endif; ?>
              </td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
<?php if ($balances): ?>
          <tfoot>
            <tr class="table-light fw-semibold">
              <td colspan="3">Totals (<?= count($balances) ?> students)</td>
              <td>UGX <?= number_format($balanceTotals['invoiced'], 2) ?></td>
              <td>UGX <?= number_format($balanceTotals['paid'], 2) ?></td>
              <td>UGX <?= number_format($balanceTotals['balance'], 2) ?></td>
              <td></td>
            </tr>
          </tfoot>
<?php endif; ?>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ Fee clearance -->
<div class="row g-3 mt-1">
<?php if ($canEdit): ?>
  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header">Set fee clearance</div>
      <div class="card-body">
<?php if (!$years || !$students): ?>
        <div class="alert alert-secondary mb-0">An academic year and active students are required before recording clearances.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/clearances/save') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Student <span class="text-danger">*</span></label>
            <select class="form-select" name="student_id" required>
              <option value="">Choose student&hellip;</option>
<?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' - ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
<?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="student_id"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Academic year <span class="text-danger">*</span></label>
              <select class="form-select" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['id'] ?>" <?= !empty($year['is_active']) ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="academic_year_id"></span>
            </div>
            <div class="col">
              <label class="form-label">Term</label>
              <select class="form-select" name="term_id">
                <option value="">Whole year</option>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="term_id"></span>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Clearance type <span class="text-danger">*</span></label>
              <select class="form-select" name="clearance_type" required>
                <option value="examination">Examination</option>
                <option value="report_card">Report card</option>
                <option value="end_of_term">End of term</option>
                <option value="transfer">Transfer</option>
                <option value="graduation">Graduation</option>
              </select>
              <span class="invalid-feedback d-block" data-error-for="clearance_type"></span>
            </div>
            <div class="col">
              <label class="form-label">Status <span class="text-danger">*</span></label>
              <select class="form-select" name="status" required>
                <option value="pending">Pending check</option>
                <option value="cleared">Cleared</option>
                <option value="blocked">Blocked</option>
                <option value="exception_granted">Exception granted</option>
              </select>
              <span class="invalid-feedback d-block" data-error-for="status"></span>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Reason / note</label>
            <input class="form-control" name="reason" maxlength="255">
            <span class="invalid-feedback d-block" data-error-for="reason"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Policy note</label>
            <input class="form-control" name="policy_note" maxlength="255" placeholder="e.g. Fee policy: clearance required for exams">
            <span class="invalid-feedback d-block" data-error-for="policy_note"></span>
          </div>
          <div class="mb-3">
            <label class="form-label">Exception reason</label>
            <input class="form-control" name="exception_reason" maxlength="255">
            <span class="invalid-feedback d-block" data-error-for="exception_reason"></span>
          </div>
          <button class="btn btn-primary" type="submit">Save clearance</button>
          <span class="text-muted small ms-2">Outstanding balance is captured from open invoices.</span>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canEdit ? 'col-lg-8' : 'col-12' ?>">
    <div class="card app-card h-100">
      <div class="card-header">Student clearance status <span class="badge text-bg-light"><?= count($clearances) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Student</th><th>Year / term</th><th>Type</th><th>Status</th><th>Balance</th><th>Checked / approved</th><th>Decision</th></tr></thead>
          <tbody>
<?php if (!$clearances): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No clearance records have been made yet.</td></tr>
<?php else: foreach ($clearances as $clearance): ?>
            <tr>
              <td><?= e($clearance['last_name'] . ' ' . $clearance['first_name']) ?>
                <div class="small text-muted"><?= e($clearance['admission_number'] . ($clearance['class_name'] ? ' - ' . $clearance['class_name'] : '')) ?></div></td>
              <td class="small"><?= e($clearance['year_name'] . ($clearance['term_name'] ? ' / ' . $clearance['term_name'] : ' - whole year')) ?></td>
              <td class="small"><?= e(ucwords(str_replace('_', ' ', $clearance['clearance_type']))) ?></td>
              <td><span class="badge text-bg-<?= $clearance['status'] === 'cleared' ? 'success' : ($clearance['status'] === 'blocked' ? 'danger' : ($clearance['status'] === 'exception_granted' ? 'info' : 'warning')) ?>"><?= e(str_replace('_', ' ', ucfirst($clearance['status']))) ?></span>
<?php if ($clearance['exception_reason']): ?><div class="small text-muted"><?= e($clearance['exception_reason']) ?></div><?php endif; ?></td>
              <td data-order="<?= (float)$clearance['outstanding_balance'] ?>">UGX <?= number_format((float)$clearance['outstanding_balance'], 2) ?></td>
              <td class="small">
                <?= e($clearance['checked_by_name'] ?? 'Not checked') ?><?= $clearance['checked_at'] ? '<div class="text-muted">' . e($clearance['checked_at']) . '</div>' : '' ?>
<?php if ($clearance['approved_by_name']): ?>
                <div class="text-muted">Approved by <?= e($clearance['approved_by_name']) ?><?= $clearance['approved_at'] ? ' &middot; ' . e($clearance['approved_at']) : '' ?></div>
<?php endif; ?>
              </td>
              <td class="text-nowrap">
<?php if ($canApprove && in_array($clearance['status'], ['pending', 'blocked'], true)): ?>
                <form method="post" action="<?= url('finance/clearances/approve') ?>" data-ajax class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="clearance_id" value="<?= (int)$clearance['id'] ?>">
                  <input type="hidden" name="action" value="cleared">
                  <button class="btn btn-success btn-sm" type="submit">Clear</button>
                </form>
                <form method="post" action="<?= url('finance/clearances/approve') ?>" data-ajax class="d-inline-flex flex-wrap gap-1 align-items-center">
                  <?= csrf_field() ?>
                  <input type="hidden" name="clearance_id" value="<?= (int)$clearance['id'] ?>">
                  <input type="hidden" name="action" value="exception_granted">
                  <input class="form-control form-control-sm" name="exception_reason" maxlength="255" placeholder="Exception reason" aria-label="Exception reason">
                  <button class="btn btn-outline-warning btn-sm" type="submit">Exception</button>
                </form>
<?php elseif ($clearance['status'] === 'cleared' || $clearance['status'] === 'exception_granted'): ?>
                <span class="text-muted small">Approved</span>
<?php else: ?>
                <span class="text-muted small">&mdash;</span>
<?php endif; ?>
              </td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ Fee reminders -->
<div class="row g-3 mt-1">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header">Send a fee reminder</div>
      <div class="card-body">
<?php if (!$years || !$students): ?>
        <div class="alert alert-secondary mb-0">An academic year and active students are required before sending reminders.</div>
<?php else: ?>
        <form method="post" action="<?= url('finance/reminders/send') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Student <span class="text-danger">*</span></label>
            <select class="form-select" name="student_id" required>
              <option value="">Choose student&hellip;</option>
<?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' - ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
<?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="student_id"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Academic year <span class="text-danger">*</span></label>
              <select class="form-select" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['id'] ?>" <?= !empty($year['is_active']) ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="academic_year_id"></span>
            </div>
            <div class="col">
              <label class="form-label">Term</label>
              <select class="form-select" name="term_id">
                <option value="">Whole year</option>
<?php foreach ($terms as $term): ?>
                <option value="<?= (int)$term['id'] ?>"><?= e($term['name']) ?></option>
<?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="term_id"></span>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Against invoice (optional)</label>
            <select class="form-select" name="invoice_id">
              <option value="">Total balance for the period</option>
<?php foreach ($reminderInvoices as $invoice): ?>
              <option value="<?= (int)$invoice['id'] ?>"><?= e($invoice['invoice_number'] . ' - ' . $invoice['last_name'] . ' ' . $invoice['first_name'] . ' - UGX ' . number_format((float)$invoice['balance'], 2)) ?></option>
<?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="invoice_id"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Channel</label>
            <select class="form-select" name="channel">
              <option value="sms">SMS</option>
              <option value="email">Email</option>
              <option value="portal">Portal</option>
              <option value="print">Print</option>
            </select>
            <span class="invalid-feedback d-block" data-error-for="channel"></span>
          </div>
          <div class="mb-3">
            <label class="form-label">Message</label>
            <textarea class="form-control" name="message" rows="3" maxlength="500" placeholder="Leave blank to use the fee_reminder SMS template."></textarea>
            <span class="invalid-feedback d-block" data-error-for="message"></span>
          </div>
          <button class="btn btn-primary" type="submit">Record reminder as sent</button>
          <span class="text-muted small ms-2">No SMS gateway is connected; the reminder is logged with its template text.</span>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-lg-8' : 'col-12' ?>">
    <div class="card app-card h-100">
      <div class="card-header">Fee reminders <span class="badge text-bg-light"><?= count($reminders) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Student</th><th>Channel</th><th>Balance at time</th><th>Sent</th><th>Message</th><th>Status</th></tr></thead>
          <tbody>
<?php if (!$reminders): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No fee reminders have been recorded yet.</td></tr>
<?php else: foreach ($reminders as $reminder): ?>
            <tr>
              <td><?= e($reminder['last_name'] . ' ' . $reminder['first_name']) ?>
                <div class="small text-muted"><?= e($reminder['admission_number'] . ' - ' . $reminder['year_name'] . ($reminder['term_name'] ? ' / ' . $reminder['term_name'] : '')) ?></div>
<?php if ($reminder['guardian_name']): ?><div class="small text-muted">Guardian: <?= e($reminder['guardian_name']) ?></div><?php endif; ?>
              </td>
              <td><span class="badge text-bg-<?= $reminder['channel'] === 'sms' ? 'primary' : 'secondary' ?>"><?= e(strtoupper($reminder['channel'])) ?></span></td>
              <td data-order="<?= (float)$reminder['balance_at_time'] ?>">UGX <?= number_format((float)$reminder['balance_at_time'], 2) ?></td>
              <td class="small"><?= e($reminder['sent_at'] ?? $reminder['created_at']) ?>
<?php if ($reminder['sent_by_name']): ?><div class="text-muted"><?= e($reminder['sent_by_name']) ?></div><?php endif; ?></td>
              <td class="small" style="max-width: 22rem;"><?= e(mb_substr((string)$reminder['message'], 0, 140)) ?><?= mb_strlen((string)$reminder['message']) > 140 ? '&hellip;' : '' ?></td>
              <td><span class="badge text-bg-<?= $reminder['status'] === 'sent' ? 'success' : ($reminder['status'] === 'failed' ? 'danger' : 'warning') ?>"><?= e(ucfirst($reminder['status'])) ?></span></td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ Receipts -->
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Issued receipts <span class="badge text-bg-light"><?= count($receipts) ?></span></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()">Print this list</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
      <thead><tr><th>Receipt</th><th>Issued</th><th>Student</th><th>Payment</th><th>Amount</th><th>Method</th><th>Narrative</th><th>Issued by</th><th style="width:12%"></th></tr></thead>
      <tbody>
<?php if (!$receipts): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No receipts have been issued yet. Verify a payment to issue one.</td></tr>
<?php else: foreach ($receipts as $receipt): ?>
            <tr>
              <td><strong><?= e($receipt['receipt_number']) ?></strong><?php if ((int)$receipt['is_void']): ?><div><span class="badge text-bg-danger">Void</span> <?= e($receipt['void_reason'] ?? '') ?></div><?php endif; ?></td>
              <td class="small"><?= e($receipt['issued_at']) ?></td>
              <td><?= e($receipt['last_name'] . ' ' . $receipt['first_name']) ?><div class="small text-muted"><?= e($receipt['admission_number']) ?></div></td>
              <td class="small"><?= e($receipt['payment_reference'] ?? 'Manual') ?><?php if ($receipt['payment_date']): ?><div class="text-muted"><?= e($receipt['payment_date']) ?></div><?php endif; ?><?php if ($receipt['external_reference']): ?><div class="text-muted"><?= e($receipt['external_reference']) ?></div><?php endif; ?></td>
              <td data-order="<?= (float)$receipt['amount'] ?>"><strong>UGX <?= number_format((float)$receipt['amount'], 2) ?></strong></td>
              <td class="small"><?= e($receipt['method_label'] ?? '') ?></td>
              <td class="small"><?= e($receipt['narrative'] ?? '') ?></td>
              <td class="small"><?= e($receipt['issued_by_name'] ?? 'System') ?><div class="text-muted"><?= e(ucfirst($receipt['delivery_status'])) ?></div></td>
              <td class="text-nowrap">
                <a class="btn btn-outline-primary btn-sm" href="<?= url('finance/receipt-pdf', ['id' => (int)$receipt['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
              </td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
