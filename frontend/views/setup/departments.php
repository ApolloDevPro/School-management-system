<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Departments</h1>
    <p class="text-muted small mb-0">Academic and non-academic departments with their heads</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit department' : 'New department' ?></div>
      <div class="card-body">
<?php if ($edit && !$canEdit): ?>
        <p class="text-muted small mb-0">You do not have permission to edit departments.</p>
<?php else: ?>
        <form method="post" action="<?= url('setup/departments/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-3">
            <label class="form-label" for="d_name">Name</label>
            <input class="form-control" id="d_name" name="name" maxlength="100" value="<?= e($edit['name'] ?? '') ?>" placeholder="Sciences" <?= $canEdit ? 'required' : 'disabled' ?>>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_type">Type</label>
            <select class="form-select" id="d_type" name="type" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="academic" <?= ($edit['type'] ?? 'academic') === 'academic' ? 'selected' : '' ?>>Academic</option>
              <option value="non-academic" <?= ($edit['type'] ?? '') === 'non-academic' ? 'selected' : '' ?>>Non-academic</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_head">Head of department</label>
            <select class="form-select" id="d_head" name="head_staff_id" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="">— None —</option>
<?php foreach ($staff as $member): ?>
              <option value="<?= (int)$member['id'] ?>" <?= (int)($edit['head_staff_id'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['full_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_desc">Description</label>
            <textarea class="form-control" id="d_desc" name="description" rows="2" maxlength="255" <?= $canEdit ? '' : 'disabled' ?>><?= e($edit['description'] ?? '') ?></textarea>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" id="d_active" name="is_active" type="checkbox" <?= !$edit || (int)$edit['is_active'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
            <label class="form-check-label" for="d_active">Active</label>
          </div>
<?php if ($canEdit): ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create department' ?></button>
<?php if ($edit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('setup/departments') ?>">Cancel</a>
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
      <div class="card-header">All departments</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Head</th>
                <th>Staff</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$departments): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No departments yet.</td></tr>
<?php endif; ?>
<?php foreach ($departments as $department): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($department['name']) ?></div>
<?php if ($department['description']): ?>
                  <div class="small text-muted"><?= e($department['description']) ?></div>
<?php endif; ?>
                </td>
                <td><span class="badge text-bg-light"><?= $department['type'] === 'academic' ? 'Academic' : 'Non-academic' ?></span></td>
                <td><?= e($department['head_name'] ?: '—') ?></td>
                <td><?= (int)$department['staff_count'] ?></td>
                <td>
<?php if ((int)$department['is_active']): ?>
                  <span class="badge text-bg-success">Active</span>
<?php else: ?>
                  <span class="badge text-bg-secondary">Inactive</span>
<?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('setup/departments', ['edit' => (int)$department['id']]) ?>">Edit</a>
                  <form method="post" action="<?= url('setup/departments/toggle') ?>" class="d-inline" data-confirm="<?= (int)$department['is_active'] ? 'Deactivate' : 'Reactivate' ?> <?= e($department['name']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$department['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit"><?= (int)$department['is_active'] ? 'Deactivate' : 'Activate' ?></button>
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
  </div>
</div>
