<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<?php
$headTitle = 'Health Dashboard';
$headIcon = 'bi-heart-pulse';
$headSubtitle = 'Sickbay visits, medical records and student welfare overview.';
require __DIR__ . '/_head.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-people" aria-hidden="true"></i><div class="stat-label">Students</div>
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-note">Active &amp; suspended</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger">
      <i class="stat-glyph bi bi-heart-pulse" aria-hidden="true"></i><div class="stat-label">Sickbay today</div>
      <div class="stat-value"><?= number_format($stats['sickbay_today']) ?></div>
      <div class="stat-note">Visits recorded today</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-arrow-repeat" aria-hidden="true"></i><div class="stat-label">Total visits</div>
      <div class="stat-value"><?= number_format($stats['visits']) ?></div>
      <div class="stat-note">All sickbay records</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger">
      <i class="stat-glyph bi bi-file-lock" aria-hidden="true"></i><div class="stat-label">Confidential records</div>
      <div class="stat-value"><?= number_format($stats['confidential']) ?></div>
      <div class="stat-note">Restricted access</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Recent sickbay visits</div>
      <div class="card-body p-0">
<?php if (!$visits): ?>
        <div class="text-muted text-center p-3">No sickbay visits recorded.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Student</th>
                <th style="width:18%">Admission no.</th>
                <th style="width:18%">Visit date</th>
                <th style="width:20%">Seen by</th>
                <th style="width:12%">Status</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($visits as $v): ?>
              <tr>
                <td><?= e($v['first_name'] . ' ' . $v['last_name']) ?></td>
                <td><?= e($v['admission_number'] ?? '—') ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($v['visit_date']))) ?></td>
                <td><?= e(trim(($v['staff_first_name'] ?? '') . ' ' . ($v['staff_last_name'] ?? '')) ?: '—') ?></td>
                <td><span class="badge text-bg-secondary"><?= e($v['status'] ?? 'recorded') ?></span></td>
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
    <div class="card app-card dashboard-actions">
      <div class="card-header">Quick actions</div>
      <div class="card-body">
        <div class="row g-2">
<?php if ($can('health', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('health') ?>" class="btn btn-outline-primary w-100">Record sickbay visit</a>
          </div>
<?php endif; ?>
<?php if ($can('health', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('health') ?>" class="btn btn-outline-secondary w-100">Medical records</a>
          </div>
<?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>
