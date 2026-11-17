<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Gate Dashboard</h1>
    <p class="text-muted small mb-0">Visitor registration, gate passes and current on-site visitors.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Visitors today</div>
      <div class="stat-value"><?= number_format($stats['visitors_today']) ?></div>
      <div class="stat-note">Signed in today</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Inside now</div>
      <div class="stat-value"><?= number_format($stats['inside']) ?></div>
      <div class="stat-note">Currently on site</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Total visitors</div>
      <div class="stat-value"><?= number_format($stats['total']) ?></div>
      <div class="stat-note">All records</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Checked out</div>
      <div class="stat-value"><?= number_format($stats['checked_out']) ?></div>
      <div class="stat-note">Completed visits</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Visitor log</div>
      <div class="card-body p-0">
<?php if (!$visitors): ?>
        <div class="text-muted text-center p-3">No visitor records yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Visitor</th>
                <th style="width:20%">Organisation</th>
                <th style="width:22%">Purpose</th>
                <th style="width:18%">Time in</th>
                <th style="width:16%">Status</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($visitors as $v): ?>
              <tr>
                <td><?= e($v['full_name']) ?> <span class="badge text-bg-light ms-1"><?= e($v['host_name'] ?? '') ?></span></td>
                <td><?= e($v['organisation'] ?? '—') ?></td>
                <td><?= e($v['purpose']) ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($v['time_in']))) ?></td>
                <td><span class="badge text-bg-<?= $v['status'] === 'inside' ? 'primary' : 'secondary' ?>"><?= e($v['status']) ?></span></td>
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
<?php if ($can('gate', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('gate') ?>" class="btn btn-outline-primary w-100">Visitor check-in</a>
          </div>
<?php endif; ?>
<?php if ($can('gate', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('gate') ?>" class="btn btn-outline-secondary w-100">Visitor check-out</a>
          </div>
<?php endif; ?>


        </div>
      </div>
    </div>
  </div>
</div>
