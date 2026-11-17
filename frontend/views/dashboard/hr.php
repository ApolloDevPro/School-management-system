<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">HR Dashboard</h1>
    <p class="text-muted small mb-0">Staff records, leave requests and payroll overview.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Staff</div>
      <div class="stat-value"><?= number_format($stats['staff']) ?></div>
      <div class="stat-note">Active, probation, contract</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Pending leave</div>
      <div class="stat-value"><?= number_format($stats['pending_leave']) ?></div>
      <div class="stat-note">Awaiting approval</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Leave requests</div>
      <div class="stat-value"><?= number_format($stats['leave_total']) ?></div>
      <div class="stat-note">All requests</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Departments</div>
      <div class="stat-value"><?= number_format($stats['departments']) ?></div>
      <div class="stat-note">Active departments</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Leave requests awaiting review</div>
      <div class="card-body p-0">
<?php if (!$leave): ?>
        <div class="text-muted text-center p-3">No leave requests pending.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Staff member</th>
                <th style="width:18%">Leave type</th>
                <th style="width:16%">Start</th>
                <th style="width:16%">End</th>
                <th style="width:14%">Status</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($leave as $l): ?>
              <tr>
                <td><?= e($l['first_name'] . ' ' . $l['last_name']) ?> <span class="badge text-bg-light ms-1"><?= e($l['staff_number']) ?></span></td>
                <td><?= e($l['leave_type']) ?></td>
                <td><?= e(date('d M Y', strtotime($l['start_date']))) ?></td>
                <td><?= e(date('d M Y', strtotime($l['end_date']))) ?></td>
                <td><span class="badge text-bg-warning"><?= e($l['status']) ?></span></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Quick actions</div>
      <div class="card-body">
        <div class="row g-2">
<?php if ($can('hr', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('hr') ?>" class="btn btn-outline-primary w-100">Add staff record</a>
          </div>
<?php endif; ?>
<?php if ($can('hr', 'approve')): ?>
          <div class="col-sm-6">
            <a href="<?= url('hr') ?>" class="btn btn-outline-secondary w-100">Review leave</a>
          </div>
<?php endif; ?>


        </div>
      </div>
    </div>
  </div>
</div>
