<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Teacher Dashboard</h1>
    <p class="text-muted small mb-0">Your classes, subjects, attendance and marks at a glance.</p>
  </div>
</div>

<?php if (empty($staffLinked)): ?>
<div class="alert alert-warning">
  This account is not linked to a staff record, so your classes, subjects and allocations cannot be shown.
  Ask an administrator to link this user account to your staff record.
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Classes</div>
      <div class="stat-value"><?= number_format($stats['classes']) ?></div>
      <div class="stat-note">You teach</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Subjects</div>
      <div class="stat-value"><?= number_format($stats['subjects']) ?></div>
      <div class="stat-note">Assigned to you</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Allocations</div>
      <div class="stat-value"><?= number_format($stats['allocations']) ?></div>
      <div class="stat-note">Subject &bull; class links</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Students</div>
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-note">Across your classes</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card app-card h-100">
      <div class="card-header">My subject allocations</div>
      <div class="card-body p-0">
<?php if (!$allocations): ?>
        <div class="text-muted text-center p-3">No subject allocations found for the active academic year.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th style="width:35%">Class</th>
                <th style="width:30%">Subject</th>
                <th style="width:18%">Stream</th>
                <th style="width:17%">Term</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($allocations as $a): ?>
              <tr>
                <td><?= e($a['class_name']) ?></td>
                <td><?= e($a['subject_name']) ?> <span class="badge text-bg-light ms-1"><?= e($a['subject_code']) ?></span></td>
                <td><?= e($a['stream_name'] ?? '—') ?></td>
                <td><?= e($a['term_name'] ?? '—') ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card app-card h-100">
      <div class="card-header">Quick actions</div>
      <div class="card-body">
        <div class="d-grid gap-2">
<?php if ($can('attendance', 'add')): ?>
          <a href="<?= url('attendance') ?>" class="btn btn-outline-primary">Record daily attendance</a>
<?php endif; ?>
<?php if ($can('examinations', 'add')): ?>
          <a href="<?= url('examinations') ?>" class="btn btn-outline-primary">Enter marks</a>
<?php endif; ?>
<?php if ($can('academics', 'add')): ?>
          <a href="<?= url('academics') ?>" class="btn btn-outline-secondary">Schemes of work</a>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>


