<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<?php
$headTitle = 'Library Dashboard';
$headIcon = 'bi-journals';
$headSubtitle = 'Books, copies, active borrowings and overdue items.';
require __DIR__ . '/_head.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-journal-bookmark" aria-hidden="true"></i><div class="stat-label">Books</div>
      <div class="stat-value"><?= number_format($stats['books']) ?></div>
      <div class="stat-note">Catalogued titles</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-collection" aria-hidden="true"></i><div class="stat-label">Total copies</div>
      <div class="stat-value"><?= number_format($stats['total_copies']) ?></div>
      <div class="stat-note">Physical items</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-success">
      <i class="stat-glyph bi bi-check-circle" aria-hidden="true"></i><div class="stat-label">Available</div>
      <div class="stat-value"><?= number_format($stats['available']) ?></div>
      <div class="stat-note">On the shelf</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger">
      <i class="stat-glyph bi bi-journal-x" aria-hidden="true"></i><div class="stat-label">Overdue</div>
      <div class="stat-value stat-<?= $stats['overdue'] > 0 ? 'danger' : 'success' ?>"><?= number_format($stats['overdue']) ?></div>
      <div class="stat-note">Past due date</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header">Active borrowings</div>
      <div class="card-body p-0">
<?php if (!$borrowings): ?>
        <div class="text-muted text-center p-3">No active borrowings.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Book</th>
                <th style="width:20%">Borrower</th>
                <th style="width:16%">Due</th>
                <th style="width:14%">Days left</th>
                <th style="width:12%">Status</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($borrowings as $b): ?>
              <tr>
                <td><?= e($b['title']) ?> <span class="badge text-bg-light ms-1"><?= e($b['accession_number'] ?? '') ?></span></td>
                <td><?= e($b['borrower_name']) ?> <span class="badge text-bg-light ms-1"><?= e($b['borrower_ref']) ?></span></td>
                <td><?= e(date('d M Y', strtotime($b['due_date']))) ?></td>
                <td class="<?= (int)$b['days_until_due'] < 0 ? 'text-danger' : 'text-muted' ?>">
                  <?= (int)$b['days_until_due'] < 0 ? 'Overdue' : (int)$b['days_until_due'] . ' left' ?></td>
                <td><span class="badge text-bg-<?= $b['status'] === 'overdue' ? 'danger' : 'primary' ?>"><?= e($b['status']) ?></span></td>
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
      <div class="card-header">Overdue borrowings</div>
      <div class="card-body p-0">
<?php if (!$overdue): ?>
        <div class="text-muted text-center p-3">No overdue items.</div>
<?php else: ?>
        <div class="list-group list-group-flush">
<?php foreach ($overdue as $o): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold"><?= e($o['title']) ?></div>
                <div class="small text-muted"><?= e($o['borrower_name']) ?> &middot; <?= e($o['borrower_ref']) ?></div>
              </div>
              <div class="text-end">
                <div class="fw-semibold text-danger"><?= (int)$o['days_overdue'] ?> days</div>
                <small class="text-muted"><?= e(date('d M Y', strtotime($o['due_date']))) ?></small>
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
<?php if ($can('library', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('library') ?>" class="btn btn-outline-primary w-100">Register books</a>
          </div>
<?php endif; ?>
<?php if ($can('library', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('library') ?>" class="btn btn-outline-secondary w-100">Issue / return books</a>
          </div>
<?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>


