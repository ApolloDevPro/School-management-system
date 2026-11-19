<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<?php
$headTitle = 'Academic Dashboard';
$headIcon = 'bi-book';
$headSubtitle = 'Curriculum, subjects, classes and overview for the director of studies.';
require __DIR__ . '/_head.php';
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-easel" aria-hidden="true"></i><div class="stat-label">Classes</div>
      <div class="stat-value"><?= number_format($stats['classes']) ?></div>
      <div class="stat-note">Active classes</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-book" aria-hidden="true"></i><div class="stat-label">Subjects</div>
      <div class="stat-value"><?= number_format($stats['subjects']) ?></div>
      <div class="stat-note">Registered subjects</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <i class="stat-glyph bi bi-people" aria-hidden="true"></i><div class="stat-label">Students</div>
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-note">Enrolled (not withdrawn)</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info">
      <i class="stat-glyph bi bi-person-badge" aria-hidden="true"></i><div class="stat-label">Staff</div>
      <div class="stat-value"><?= number_format($stats['staff']) ?></div>
      <div class="stat-note">Teaching &amp; support</div>
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
      <div class="card-header">Subjects without class allocation</div>
      <div class="card-body p-0">
<?php if (!$unallocated): ?>
        <p class="text-muted small p-3 mb-0">All active subjects are allocated to classes for the active year.</p>
<?php else: ?>
        <div class="list-group list-group-flush">
<?php foreach ($unallocated as $u): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <span><?= e($u['subject_name']) ?></span>
              <span class="badge text-bg-secondary"><?= e($u['class_name']) ?></span>
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
<?php if ($can('academics', 'add') || $can('academics', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('academics') ?>" class="btn btn-outline-primary w-100">Curriculum &amp; subjects</a>
          </div>
<?php endif; ?>
<?php if ($can('students', 'view')): ?>
          <div class="col-sm-6">
            <a href="<?= url('students') ?>" class="btn btn-outline-secondary w-100">Student records</a>
          </div>
<?php endif; ?>
<?php if ($can('examinations', 'view')): ?>
          <div class="col-sm-6">
            <a href="<?= url('examinations') ?>" class="btn btn-outline-secondary w-100">Exams &amp; results</a>
          </div>
<?php endif; ?>
<?php if ($can('attendance', 'view')): ?>
          <div class="col-sm-6">
            <a href="<?= url('attendance') ?>" class="btn btn-outline-secondary w-100">Attendance</a>
          </div>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
