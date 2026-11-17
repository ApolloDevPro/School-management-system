<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Student promotion</h1>
    <p class="text-muted small mb-0">Move students to the next class at the end of the year</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header">New promotion batch</div>
      <div class="card-body">
<?php if (!$canEdit): ?>
        <p class="text-muted small mb-0">You do not have permission to run promotions.</p>
<?php else: ?>
        <form method="post" action="<?= url('promotions/store') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="p_name">Batch name</label>
            <input class="form-control" id="p_name" name="name" maxlength="120" placeholder="End of 2026 promotion" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="p_year">Academic year</label>
            <select class="form-select" id="p_year" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
              <option value="<?= (int)$year['id'] ?>" <?= (int)$year['is_active'] ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="p_level">Level being promoted</label>
            <select class="form-select" id="p_level" name="from_level" required>
<?php foreach (['S1', 'S2', 'S3', 'S4', 'S5'] as $level): ?>
              <option value="<?= $level ?>"><?= $level ?> → <?= $level === 'S5' ? 'S6' : 'S' . ((int)substr($level, 1) + 1) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="p_rules">Promotion rules / criteria</label>
            <textarea class="form-control" id="p_rules" name="promotion_rules" rows="2" placeholder="e.g. Passed at least 6 subjects"></textarea>
          </div>
          <button class="btn btn-primary" type="submit">Create batch</button>
        </form>
        <p class="form-text mb-0">Processing a batch promotes every active student in the selected level. S4 and S5 completers are marked as completed.</p>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card app-card mb-3">
      <div class="card-header">Promotion batches</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Batch</th><th>Year</th><th>Level</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
<?php if (!$batches): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No promotion batches yet.</td></tr>
<?php endif; ?>
<?php foreach ($batches as $row): ?>
              <tr>
                <td class="fw-semibold"><?= e($row['name']) ?></td>
                <td><?= e($row['year_name']) ?></td>
                <td><?= e($row['from_level']) ?></td>
                <td><?= (int)$row['record_count'] ?></td>
                <td><span class="badge text-bg-<?= $row['status'] === 'completed' ? 'success' : 'light' ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('students/promotions', ['batch_id' => (int)$row['id']]) ?>">View</a>
<?php if ($canEdit && $row['status'] !== 'completed'): ?>
                  <form method="post" action="<?= url('promotions/process') ?>" class="d-inline" data-confirm="Process this batch? Every active student in <?= e($row['from_level']) ?> will be promoted. This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="batch_id" value="<?= (int)$row['id'] ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit">Process</button>
                  </form>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

<?php if ($batch): ?>
    <div class="card app-card">
      <div class="card-header"><?= e($batch['name']) ?> — results</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Admission</th><th>Student</th><th>From</th><th>To</th><th>Decision</th></tr></thead>
            <tbody>
<?php foreach ($records as $record): ?>
              <tr>
                <td><?= e($record['admission_number']) ?></td>
                <td><?= e($record['last_name'] . ' ' . $record['first_name']) ?></td>
                <td><?= e($record['from_class_name']) ?></td>
                <td><?= e($record['to_class_name'] ?? '—') ?></td>
                <td><span class="badge text-bg-light"><?= e($record['decision']) ?></span></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
<?php endif; ?>
  </div>
</div>
