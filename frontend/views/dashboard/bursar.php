<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<?php
$headTitle = 'Finance Dashboard';
$headIcon = 'bi-cash-stack';
$headSubtitle = 'Fee invoices, payments, outstanding balances and pending verifications.';
require __DIR__ . '/_head.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-receipt" aria-hidden="true"></i><div class="stat-label">Invoices</div>
      <div class="stat-value"><?= number_format($stats['invoices']) ?></div>
      <div class="stat-note">Fee invoices issued</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger">
      <i class="stat-glyph bi bi-exclamation-circle" aria-hidden="true"></i><div class="stat-label">Outstanding</div>
      <div class="stat-value stat-money"><?= number_format($stats['outstanding']) ?></div>
      <div class="stat-note"><?= e(school_row()['currency_symbol'] ?? 'Ugx') ?> unpaid balances</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-success">
      <i class="stat-glyph bi bi-check-circle" aria-hidden="true"></i><div class="stat-label">Paid</div>
      <div class="stat-value stat-money"><?= number_format($stats['paid']) ?></div>
      <div class="stat-note"><?= e(school_row()['currency_symbol'] ?? 'Ugx') ?> received</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-warning">
      <i class="stat-glyph bi bi-hourglass-split" aria-hidden="true"></i><div class="stat-label">Pending payments</div>
      <div class="stat-value"><?= number_format($stats['pending']) ?></div>
      <div class="stat-note">Awaiting verification</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header">Top debtors (outstanding balances)</div>
      <div class="card-body p-0">
<?php if (!$debtors): ?>
        <div class="text-muted text-center p-3">No outstanding balances.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Student</th>
                <th style="width:16%">Class</th>
                <th style="width:18%">Balance</th>
                <th style="width:16%">Overdue</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($debtors as $d): ?>
              <tr>
                <td><?= e($d['first_name'] . ' ' . $d['last_name']) ?> <span class="badge text-bg-light ms-1"><?= e($d['admission_number']) ?></span></td>
                <td><?= e($d['class_name'] ?? '—') ?></td>
                <td><?= number_format((float)$d['balance']) ?> <?= e(school_row()['currency_symbol'] ?? 'Ugx') ?></td>
                <td class="<?= ((int)$d['days_overdue'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>">
                  <?= (int)($d['days_overdue'] ?? 0) > 0 ? (int)$d['days_overdue'] . ' days' : '—' ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">Recent payments</div>
      <div class="card-body p-0">
<?php if (!$payments): ?>
        <div class="text-muted text-center p-3">No recent payments.</div>
<?php else: ?>
        <div class="list-group list-group-flush">
<?php foreach ($payments as $p): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                <div class="small text-muted"><?= e($p['admission_number']) ?> &middot; <?= e($p['channel'] ?? '') ?></div>
              </div>
              <div class="text-end">
                <div class="fw-semibold"><?= number_format((float)($p['amount_local'] ?? 0)) ?></div>
                <span class="badge text-bg-<?= $p['status'] === 'verified' ? 'success' : 'warning' ?>"><?= e($p['status']) ?></span>
              </div>
            </div>
          </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card dashboard-actions">
      <div class="card-header">Quick actions</div>
      <div class="card-body">
        <div class="row g-2">
<?php if ($can('finance', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('finance') ?>" class="btn btn-outline-primary w-100">Issue invoice / record payment</a>
          </div>
<?php endif; ?>
<?php if ($can('finance', 'approve')): ?>
          <div class="col-sm-6">
            <a href="<?= url('finance') ?>" class="btn btn-outline-secondary w-100">Verify payments</a>
          </div>
<?php endif; ?>
<?php if ($can('online_payments', 'view')): ?>
          <div class="col-sm-6">
            <a href="<?= url('finance_online') ?>" class="btn btn-outline-secondary w-100">Online payments</a>
          </div>
<?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>


