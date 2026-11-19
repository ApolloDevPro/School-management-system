<?php /* UNEB candidates and national results. */
$canAdd = $canAdd ?? false;
$canEdit = $canEdit ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">UNEB Management</h1>
      <p class="text-muted small mb-0">UNEB candidate registration, continuous assessment submission and national results.</p>
    </div>
    <?php if ($canAdd): ?>
    <a class="btn btn-primary" href="<?= url('uneb/register') ?>"><i class="bi bi-person-plus"></i> Register Candidate</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Total Candidates</div>
      <div class="stat-value"><?= number_format($stats['total_candidates'] ?? 0) ?></div>
      <div class="stat-note">All registrations</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Registered</div>
      <div class="stat-value"><?= number_format($stats['registered'] ?? 0) ?></div>
      <div class="stat-note">Awaiting entry</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">CA Submitted</div>
      <div class="stat-value"><?= number_format($stats['casubmitted'] ?? 0) ?></div>
      <div class="stat-note">CA sent to UNEB</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Results Recorded</div>
      <div class="stat-value"><?= number_format($stats['results_recorded'] ?? 0) ?></div>
      <div class="stat-note">National results</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Candidates</span>
        <form method="get" action="<?= url('uneb') ?>" class="d-flex gap-2">
          <select class="form-select form-select-sm" name="level_id">
            <option value="0">All levels</option>
            <?php foreach ($levels as $level): ?>
            <option value="<?= (int)$level['id'] ?>" <?= $selectedLevel === (int)$level['id'] ? 'selected' : '' ?>><?= e($level['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary btn-sm" type="submit">Filter</button>
        </form>
      </div>
      <div class="card-body p-0">
<?php if (!$candidates): ?>
        <div class="text-muted text-center p-4">No UNEB candidates registered yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Index Number</th><th>Full Name</th><th>Gender</th><th>Level</th><th>Status</th>
                <th style="width:16%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($candidates as $c): ?>
              <tr>
                <td><?= e($c['index_number']) ?></td>
                <td><?= e($c['full_name']) ?></td>
                <td><?= e(ucfirst($c['gender'])) ?></td>
                <td><?= e($c['level_name'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= $c['status'] === 'registered' ? 'primary' : ($c['status'] === 'entered' ? 'success' : 'secondary') ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                <td class="text-nowrap">
<?php if ($canEdit): ?>
                  <a class="btn btn-outline-primary btn-sm" href="<?= url('uneb/edit', ['id' => (int)$c['id']]) ?>"><i class="bi bi-pencil"></i></a>
                  <form method="post" action="<?= url('uneb/delete') ?>" class="d-inline" data-confirm="Delete this candidate?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
<?php endif; ?>
                </td>
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
