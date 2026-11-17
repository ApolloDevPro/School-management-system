<?php
$user = Auth::user();
$can = function (string $module, string $action = 'view') use ($user): bool {
    return Auth::can($module, $action);
};
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Registrar Dashboard</h1>
    <p class="text-muted small mb-0">Admissions, student records, enrolment and class allocation overview.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Students</div>
      <div class="stat-value"><?= number_format($stats['students']) ?></div>
      <div class="stat-note">All records (not withdrawn)</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Active</div>
      <div class="stat-value"><?= number_format($stats['active']) ?></div>
      <div class="stat-note">Currently enrolled</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Pending applications</div>
      <div class="stat-value"><?= number_format($stats['applicants']) ?></div>
      <div class="stat-note">Awaiting decision</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Classes</div>
      <div class="stat-value"><?= number_format($stats['classes']) ?></div>
      <div class="stat-note">Active classes</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Recently added students</div>
      <div class="card-body p-0">
<?php if (!$recent): ?>
        <div class="text-muted text-center p-3">No student records yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th>Student</th>
                <th style="width:18%">Admission no.</th>
                <th style="width:16%">Class</th>
                <th style="width:16%">Status</th>
                <th style="width:18%">Added</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($recent as $r): ?>
              <tr>
                <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= e($r['admission_number'] ?? '—') ?></td>
                <td><?= e($r['class_name'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($r['status']) ?></span></td>
                <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
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
<?php if ($can('students', 'add')): ?>
          <div class="col-sm-6">
            <a href="<?= url('students') ?>" class="btn btn-outline-primary w-100">Register student</a>
          </div>
<?php endif; ?>
<?php if ($can('admissions', 'view')): ?>
          <div class="col-sm-6">
            <a href="<?= url('admissions') ?>" class="btn btn-outline-secondary w-100">Admissions</a>
          </div>
<?php endif; ?>
<?php if ($can('students', 'edit')): ?>
          <div class="col-sm-6">
            <a href="<?= url('students/allocation') ?>" class="btn btn-outline-secondary w-100">Class allocation</a>
          </div>
<?php endif; ?>
<?php if ($can('students', 'view')): ?>
          <div class="col-sm-6">
            <a href="<?= url('guardians') ?>" class="btn btn-outline-secondary w-100">Parents &amp; guardians</a>
          </div>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
