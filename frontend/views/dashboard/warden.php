<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<?php
$headTitle = 'Boarding Dashboard';
$headIcon = 'bi-house-door';
$headSubtitle = 'Dormitories, bed allocation and boarder status.';
require __DIR__ . '/_head.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-buildings" aria-hidden="true"></i><div class="stat-label">Dormitories</div>
      <div class="stat-value"><?= number_format($stats['dormitories']) ?></div>
      <div class="stat-note">Active dorms</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-door-open" aria-hidden="true"></i><div class="stat-label">Registrations</div>
      <div class="stat-value"><?= number_format($stats['registrations']) ?></div>
      <div class="stat-note">Boarding registrations</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-success">
      <i class="stat-glyph bi bi-box-arrow-in-right" aria-hidden="true"></i><div class="stat-label">Checked in</div>
      <div class="stat-value"><?= number_format($stats['checked_in']) ?></div>
      <div class="stat-note">Currently resident</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-house-heart" aria-hidden="true"></i><div class="stat-label">Boarders</div>
      <div class="stat-value"><?= number_format($stats['boarders']) ?></div>
      <div class="stat-note">Not checked out</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Recent boarding registrations</div>
      <div class="card-body p-0">
<?php if (!$registrations): ?>
        <div class="text-muted text-center p-3">No boarding registrations yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Student</th>
                <th style="width:18%">Admission no.</th>
                <th style="width:20%">Dormitory</th>
                <th style="width:16%">Year</th>
                <th style="width:16%">Term</th>
                <th style="width:16%">Status</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($registrations as $r): ?>
              <tr>
                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= e($r['admission_number']) ?></td>
                <td><?= e($r['dormitory_name'] ?? '—') ?></td>
                <td><?= e($r['year_name']) ?></td>
                <td><?= e($r['term_name']) ?></td>
                <td><span class="badge text-bg-<?= $r['status'] === 'checked_in' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
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
<?php if ($can('boarding', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('boarding') ?>" class="btn btn-outline-primary w-100">Register boarder</a>
          </div>
<?php endif; ?>
<?php if ($can('boarding', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('boarding') ?>" class="btn btn-outline-secondary w-100">Update status</a>
          </div>
<?php endif; ?>


        </div>
      </div>
    </div>
  </div>
</div>
