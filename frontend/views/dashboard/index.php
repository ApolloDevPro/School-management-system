<?php /* Dashboard dispatcher: renders the dashboard variant that matches the signed-in role. */ ?>
<?php
$headTitle = 'Dashboard';
$headIcon = 'bi-grid-1x2';
$headSubtitle = 'Welcome back, ' . e(Auth::user()['full_name'] ?? '') . ' &middot; ' . e(Auth::user()['role_name'] ?? '');
require __DIR__ . '/_head.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-people" aria-hidden="true"></i><div class="stat-label">Students</div>
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-note">Active enrolment</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-person-badge" aria-hidden="true"></i><div class="stat-label">Staff</div>
      <div class="stat-value"><?= number_format($stats['staff']) ?></div>
      <div class="stat-note">Teaching &amp; non-teaching</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-shield-lock" aria-hidden="true"></i><div class="stat-label">System users</div>
      <div class="stat-value"><?= number_format($stats['users']) ?></div>
      <div class="stat-note">Login accounts</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger">
      <i class="stat-glyph bi bi-cash-stack" aria-hidden="true"></i><div class="stat-label">Fees outstanding</div>
      <div class="stat-value stat-money"><?= number_format($stats['fees']) ?></div>
      <div class="stat-note"><?= e(school_row()['currency_symbol'] ?? 'Ugx') ?> &middot; unpaid balances</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header">Students by class</div>
      <div class="card-body">
        <canvas id="classChart" height="220"
                data-labels='<?= e(json_encode($labels)) ?>'
                data-values='<?= e(json_encode($values)) ?>'></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">Recent activity</div>
      <div class="card-body p-0">
<?php if (!$recent): ?>
        <p class="text-muted small p-3 mb-0">No activity recorded yet.</p>
<?php else: ?>
        <ul class="activity-list mb-0">
<?php foreach ($recent as $entry): ?>
          <li>
            <div class="activity-line">
              <strong><?= e($entry['user_name'] ?? 'System') ?></strong>
              <span class="activity-action"><?= e($entry['action']) ?></span>
            </div>
            <div class="activity-meta small text-muted"><?= e($entry['summary']) ?> &middot; <?= e(date('d M Y, H:i', strtotime($entry['created_at']))) ?></div>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>
