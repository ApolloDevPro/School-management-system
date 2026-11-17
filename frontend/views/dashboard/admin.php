<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view'): bool { return Auth::can($module, $action); };
$currency = e(school_row()['currency_symbol'] ?? 'Ugx');
$alerts = [];
if (!$activeYear) { $alerts[] = ['type' => 'warning', 'text' => 'No active academic year set.', 'url' => url('academics'), 'label' => 'Set up academics', 'show' => $can('academics', 'edit') || $can('academics', 'view')]; }
if (!$activeTerm) { $alerts[] = ['type' => 'warning', 'text' => 'No active term set.', 'url' => url('academics'), 'label' => 'Configure term', 'show' => $can('academics', 'edit') || $can('academics', 'view')]; }
$alerts = array_values(array_filter($alerts, fn($a) => !empty($a['show'])));
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Dashboard</h1>
    <p class="text-muted small mb-0">Welcome back, <?= e($user['full_name'] ?? '') ?> &middot; <?= e($user['role_name'] ?? '') ?></p>
  </div>
  <div class="text-end">
    <span class="badge text-bg-<?= $activeYear ? 'success' : 'warning' ?>"><?= $activeYear ? 'Year active' : 'No active year' ?></span>
    <span class="badge text-bg-<?= $activeTerm ? 'success' : 'warning' ?> ms-2"><?= $activeTerm ? 'Term active' : 'No active term' ?></span>
  </div>
</div>

<?php if ($alerts): ?>
<div class="mb-3">
  <?php foreach ($alerts as $a): ?>
    <div class="alert alert-<?= e($a['type']) ?> d-flex justify-content-between align-items-center py-2 mb-2">
      <span><?= e($a['text']) ?></span>
      <a class="btn btn-sm btn-outline-<?= e($a['type'] === 'info' ? 'primary' : 'dark') ?>" href="<?= e($a['url']) ?>"><?= e($a['label']) ?></a>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><a class="text-decoration-none" href="<?= url('students') ?>"><div class="card stat-card tone-primary h-100"><div class="stat-label">Students</div><div class="stat-value"><?= number_format((int)$stats['students']) ?></div><div class="stat-note">Active enrolment</div></div></a></div>
  <div class="col-6 col-xl-3"><a class="text-decoration-none" href="<?= url('hr') ?>"><div class="card stat-card tone-primary h-100"><div class="stat-label">Staff</div><div class="stat-value"><?= number_format((int)$stats['staff']) ?></div><div class="stat-note">Teaching &amp; non-teaching</div></div></a></div>
  <div class="col-6 col-xl-3"><a class="text-decoration-none" href="<?= url('users') ?>"><div class="card stat-card tone-primary h-100"><div class="stat-label">System users</div><div class="stat-value"><?= number_format((int)$stats['users']) ?></div><div class="stat-note">Active accounts</div></div></a></div>
  <div class="col-6 col-xl-3"><a class="text-decoration-none" href="<?= url('finance') ?>"><div class="card stat-card tone-primary h-100"><div class="stat-label">Fees outstanding</div><div class="stat-value stat-money"><?= number_format((float)$stats['fees']) ?></div><div class="stat-note"><?= $currency ?> &middot; <?= number_format((int)$stats['invoices']) ?> invoices</div></div></a></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= url('finance') ?>"><div class="card stat-card h-100"><div class="stat-label">Fees paid</div><div class="stat-value stat-money"><?= number_format((float)$stats['paid']) ?></div><div class="stat-note"><?= $currency ?></div></div></a></div>
  <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= url('admissions') ?>"><div class="card stat-card h-100"><div class="stat-label">Applicants</div><div class="stat-value"><?= number_format((int)$stats['applicants']) ?></div><div class="stat-note">Pending/review</div></div></a></div>
  <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= url('attendance') ?>"><div class="card stat-card h-100"><div class="stat-label">Present today</div><div class="stat-value"><?= number_format((int)$stats['attendance']) ?></div><div class="stat-note">Attendance records</div></div></a></div>
  <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= url('library') ?>"><div class="card stat-card h-100"><div class="stat-label">Overdue books</div><div class="stat-value"><?= number_format((int)$stats['overdue']) ?></div><div class="stat-note">Borrowings</div></div></a></div>
  <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= url('inventory') ?>"><div class="card stat-card h-100"><div class="stat-label">Low stock</div><div class="stat-value"><?= number_format((int)$stats['low_stock']) ?></div><div class="stat-note">Items at/below reorder</div></div></a></div>
  <div class="col-6 col-md-4 col-xl-2"><a class="text-decoration-none" href="<?= url('security') ?>"><div class="card stat-card h-100"><div class="stat-label">Open tickets</div><div class="stat-value"><?= number_format((int)$stats['tickets']) ?></div><div class="stat-note">Support &amp; security</div></div></a></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center"><span>Students by class</span><a class="small" href="<?= url('reports') ?>">Reports</a></div>
      <div class="card-body">
        <?php if (empty($labels)): ?>
          <p class="text-muted small mb-0">No class data available. <a href="<?= url('academics') ?>">Set up classes</a> and enrol students.</p>
        <?php else: ?>
          <canvas id="classChart" height="220" data-labels='<?= e(json_encode($labels)) ?>' data-values='<?= e(json_encode($values)) ?>'></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">Quick actions</div>
      <div class="card-body">
        <div class="row g-2">
          <?php if ($can('administration', 'view')): ?><div class="col-sm-6"><a href="<?= url('users') ?>" class="btn btn-outline-primary w-100">Users &amp; roles</a></div><?php endif; ?>
          <?php if ($can('students', 'view')): ?><div class="col-sm-6"><a href="<?= url('students') ?>" class="btn btn-outline-secondary w-100">Students</a></div><?php endif; ?>
          <?php if ($can('admissions', 'view')): ?><div class="col-sm-6"><a href="<?= url('admissions') ?>" class="btn btn-outline-secondary w-100">Admissions</a></div><?php endif; ?>
          <?php if ($can('academics', 'view')): ?><div class="col-sm-6"><a href="<?= url('academics') ?>" class="btn btn-outline-secondary w-100">Academics</a></div><?php endif; ?>
          <?php if ($can('finance', 'view')): ?><div class="col-sm-6"><a href="<?= url('finance') ?>" class="btn btn-outline-secondary w-100">Finance</a></div><?php endif; ?>
          <?php if ($can('reports', 'view') || $can('administration', 'view')): ?><div class="col-sm-6"><a href="<?= url('reports') ?>" class="btn btn-outline-secondary w-100">Reports</a></div><?php endif; ?>
          <?php if ($can('communication', 'view')): ?><div class="col-sm-6"><a href="<?= url('communication') ?>" class="btn btn-outline-secondary w-100">Communication</a></div><?php endif; ?>
          <?php if ($can('security', 'view')): ?><div class="col-sm-6"><a href="<?= url('security') ?>" class="btn btn-outline-secondary w-100">Security &amp; backups</a></div><?php endif; ?>
          <?php if ($can('compliance', 'view')): ?><div class="col-sm-6"><a href="<?= url('compliance') ?>" class="btn btn-outline-secondary w-100">Compliance</a></div><?php endif; ?>
          <?php if ($can('settings', 'view')): ?><div class="col-sm-6"><a href="<?= url('settings') ?>" class="btn btn-outline-secondary w-100">Settings</a></div><?php endif; ?>
        </div>
        <hr class="my-3">
        <div class="small text-muted">
          <div><strong>Pending leave:</strong> <?= number_format((int)$stats['leave']) ?><?php if ($can('hr', 'view')): ?> &middot; <a href="<?= url('hr') ?>">Review</a><?php endif; ?></div>
          <div><strong>Locked accounts (24h/login):</strong> <?= number_format((int)$stats['locked']) ?> &middot; <strong>Failed logins (24h):</strong> <?= number_format((int)$stats['failed_logins']) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Recent activity</div>
      <div class="card-body p-0">
        <?php if (!$recent): ?>
          <p class="text-muted small p-3 mb-0">No activity recorded yet.</p>
        <?php else: ?>
          <ul class="activity-list mb-0">
            <?php foreach ($recent as $entry): ?>
              <li>
                <div class="activity-line"><strong><?= e($entry['user_name'] ?? 'System') ?></strong> <span class="activity-action"><?= e($entry['action']) ?></span></div>
                <div class="activity-meta small text-muted"><?= e($entry['summary'] ?? '') ?> &middot; <?= !empty($entry['created_at']) ? e(date('d M Y, H:i', strtotime($entry['created_at']))) : '' ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
