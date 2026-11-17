<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Academic years</h1>
    <p class="text-muted small mb-0">Create years and mark which one is currently active</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit academic year' : 'New academic year' ?></div>
      <div class="card-body">
<?php if ($edit && !$canEdit): ?>
        <p class="text-muted small mb-0">You do not have permission to edit academic years.</p>
<?php else: ?>
        <form method="post" action="<?= url('setup/years/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-3">
            <label class="form-label" for="y_name">Year name</label>
            <input class="form-control" id="y_name" name="name" maxlength="20" value="<?= e($edit['name'] ?? '') ?>" placeholder="2026/2027" <?= $canEdit ? 'required' : 'disabled' ?>>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="y_start">Start date</label>
              <input class="form-control" id="y_start" name="start_date" type="date" value="<?= e($edit['start_date'] ?? '') ?>" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
            <div class="col-6">
              <label class="form-label" for="y_end">End date</label>
              <input class="form-control" id="y_end" name="end_date" type="date" value="<?= e($edit['end_date'] ?? '') ?>" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="y_status">Status</label>
            <select class="form-select" id="y_status" name="status" <?= $canEdit ? '' : 'disabled' ?>>
<?php foreach (['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label): ?>
              <option value="<?= $value ?>" <?= ($edit['status'] ?? 'planned') === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="y_notes">Notes</label>
            <textarea class="form-control" id="y_notes" name="notes" rows="2" maxlength="255" <?= $canEdit ? '' : 'disabled' ?>><?= e($edit['notes'] ?? '') ?></textarea>
          </div>
<?php if ($canEdit): ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create year' ?></button>
<?php if ($edit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('setup/years') ?>">Cancel</a>
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
      <div class="card-header">All academic years</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Start</th>
                <th>End</th>
                <th>Status</th>
                <th>Terms</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$years): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No academic years yet. Create the first one.</td></tr>
<?php endif; ?>
<?php foreach ($years as $year): ?>
              <tr>
                <td class="fw-semibold"><?= e($year['name']) ?></td>
                <td><?= e(date('d M Y', strtotime($year['start_date']))) ?></td>
                <td><?= e(date('d M Y', strtotime($year['end_date']))) ?></td>
                <td>
<?php if ((int)$year['is_active']): ?>
                  <span class="badge text-bg-success">Active</span>
<?php else: ?>
                  <span class="badge text-bg-light"><?= e(ucfirst($year['status'])) ?></span>
<?php endif; ?>
                </td>
                <td><?= count(Term::forYear((int)$year['id'])) ?></td>
                <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
<?php if (!(int)$year['is_active']): ?>
                  <form method="post" action="<?= url('setup/years/activate') ?>" class="d-inline" data-confirm="Make <?= e($year['name']) ?> the active academic year? The current active year will be changed.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$year['id'] ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit">Make active</button>
                  </form>
<?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('setup/years', ['edit' => (int)$year['id']]) ?>">Edit</a>
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
