<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Terms</h1>
    <p class="text-muted small mb-0">Define the terms of each academic year and mark the active term</p>
  </div>
  <form method="get" action="<?= url('setup/terms') ?>" class="d-flex gap-2">
    <select class="form-select form-select-sm" name="year_id" data-submit-on-change>
<?php foreach ($years as $year): ?>
      <option value="<?= (int)$year['id'] ?>" <?= (int)$year['id'] === $yearId ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
    </select>
  </form>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit term' : 'New term' ?></div>
      <div class="card-body">
<?php if ($edit && !$canEdit): ?>
        <p class="text-muted small mb-0">You do not have permission to edit terms.</p>
<?php elseif (!$years): ?>
        <p class="text-muted small mb-0">Create an academic year first.</p>
<?php else: ?>
        <form method="post" action="<?= url('setup/terms/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <input type="hidden" name="academic_year_id" value="<?= $yearId ?>">
          <div class="mb-3">
            <label class="form-label" for="t_name">Term name</label>
            <input class="form-control" id="t_name" name="name" maxlength="30" value="<?= e($edit['name'] ?? '') ?>" placeholder="Term 1" <?= $canEdit ? 'required' : 'disabled' ?>>
          </div>
          <div class="mb-3">
            <label class="form-label" for="t_number">Term number</label>
            <select class="form-select" id="t_number" name="term_number" <?= $canEdit ? 'required' : 'disabled' ?>>
<?php for ($n = 1; $n <= 4; $n++): ?>
              <option value="<?= $n ?>" <?= (int)($edit['term_number'] ?? 1) === $n ? 'selected' : '' ?>><?= $n ?></option>
<?php endfor; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="t_start">Start date</label>
              <input class="form-control" id="t_start" name="start_date" type="date" value="<?= e($edit['start_date'] ?? '') ?>" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
            <div class="col-6">
              <label class="form-label" for="t_end">End date</label>
              <input class="form-control" id="t_end" name="end_date" type="date" value="<?= e($edit['end_date'] ?? '') ?>" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="t_report">Report date</label>
              <input class="form-control" id="t_report" name="report_date" type="date" value="<?= e($edit['report_date'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-6">
              <label class="form-label" for="t_status">Status</label>
              <select class="form-select" id="t_status" name="status" <?= $canEdit ? '' : 'disabled' ?>>
<?php foreach (['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($edit['status'] ?? 'planned') === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
<?php if ($canEdit): ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create term' ?></button>
<?php if ($edit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('setup/terms', ['year_id' => $yearId]) ?>">Cancel</a>
<?php endif; ?>
          </div>
<?php endif; ?>
        </form>
<?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Terms in the selected year</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead>
              <tr>
                <th>Term</th>
                <th>Number</th>
                <th>Start</th>
                <th>End</th>
                <th>Reports</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$terms): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No terms for this year yet.</td></tr>
<?php endif; ?>
<?php foreach ($terms as $term): ?>
              <tr>
                <td class="fw-semibold"><?= e($term['name']) ?></td>
                <td><?= (int)$term['term_number'] ?></td>
                <td><?= e(date('d M Y', strtotime($term['start_date']))) ?></td>
                <td><?= e(date('d M Y', strtotime($term['end_date']))) ?></td>
                <td><?= $term['report_date'] ? e(date('d M Y', strtotime($term['report_date']))) : '—' ?></td>
                <td>
<?php if ((int)$term['is_active']): ?>
                  <span class="badge text-bg-success">Active</span>
<?php else: ?>
                  <span class="badge text-bg-light"><?= e(ucfirst($term['status'])) ?></span>
<?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
<?php if (!(int)$term['is_active']): ?>
                  <form method="post" action="<?= url('setup/terms/activate') ?>" class="d-inline" data-confirm="Make <?= e($term['name']) ?> the active term?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$term['id'] ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit">Make active</button>
                  </form>
<?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('setup/terms', ['year_id' => $yearId, 'edit' => (int)$term['id']]) ?>">Edit</a>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
