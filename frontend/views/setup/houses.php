<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Houses</h1>
    <p class="text-muted small mb-0">School houses, their colours and house masters</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit house' : 'New house' ?></div>
      <div class="card-body">
<?php if ($edit && !$canEdit): ?>
        <p class="text-muted small mb-0">You do not have permission to edit houses.</p>
<?php else: ?>
        <form method="post" action="<?= url('setup/houses/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="mb-3">
            <label class="form-label" for="h_name">Name</label>
            <input class="form-control" id="h_name" name="name" maxlength="60" value="<?= e($edit['name'] ?? '') ?>" placeholder="Red House" <?= $canEdit ? 'required' : 'disabled' ?>>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="h_colour">Colour</label>
              <input class="form-control form-control-color" id="h_colour" name="colour" type="color" value="<?= e($edit['colour'] ?? '#ef4444') ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" id="h_active" name="is_active" type="checkbox" <?= !$edit || (int)$edit['is_active'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <label class="form-check-label" for="h_active">Active</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="h_motto">Motto</label>
            <input class="form-control" id="h_motto" name="motto" maxlength="160" value="<?= e($edit['motto'] ?? '') ?>" <?= $canEdit ? '' : 'disabled' ?>>
          </div>
          <div class="mb-3">
            <label class="form-label" for="h_master">House master / mistress</label>
            <select class="form-select" id="h_master" name="house_master_staff_id" <?= $canEdit ? '' : 'disabled' ?>>
              <option value="">— None —</option>
<?php foreach ($staff as $member): ?>
              <option value="<?= (int)$member['id'] ?>" <?= (int)($edit['house_master_staff_id'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['full_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
<?php if ($canEdit): ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create house' ?></button>
<?php if ($edit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('setup/houses') ?>">Cancel</a>
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
      <div class="card-header">All houses</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead>
              <tr>
                <th>House</th>
                <th>Colour</th>
                <th>House master</th>
                <th>Students</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$houses): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No houses yet.</td></tr>
<?php endif; ?>
<?php foreach ($houses as $house): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($house['name']) ?></div>
<?php if ($house['motto']): ?>
                  <div class="small text-muted"><?= e($house['motto']) ?></div>
<?php endif; ?>
                </td>
                <td>
<?php if ($house['colour']): ?>
                  <span class="badge" style="background:<?= e($house['colour']) ?>">&nbsp;</span>
<?php else: ?>
                  —
<?php endif; ?>
                </td>
                <td><?= e($house['house_master_name'] ?: '—') ?></td>
                <td><?= number_format((int)$house['student_count']) ?></td>
                <td>
<?php if ((int)$house['is_active']): ?>
                  <span class="badge text-bg-success">Active</span>
<?php else: ?>
                  <span class="badge text-bg-secondary">Inactive</span>
<?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('setup/houses', ['edit' => (int)$house['id']]) ?>">Edit</a>
                  <form method="post" action="<?= url('setup/houses/toggle') ?>" class="d-inline" data-confirm="<?= (int)$house['is_active'] ? 'Deactivate' : 'Reactivate' ?> <?= e($house['name']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$house['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit"><?= (int)$house['is_active'] ? 'Deactivate' : 'Activate' ?></button>
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
