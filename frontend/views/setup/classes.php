<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Classes &amp; streams</h1>
    <p class="text-muted small mb-0">S1 to S6 classes, their streams, capacities and class teachers</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card mb-3">
      <div class="card-header"><?= $edit ? 'Edit class' : 'New class' ?></div>
      <div class="card-body">
<?php if ($edit && !$canEdit): ?>
        <p class="text-muted small mb-0">You do not have permission to edit classes.</p>
<?php else: ?>
        <form method="post" action="<?= url('setup/classes/save') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="c_name">Class name</label>
              <input class="form-control" id="c_name" name="name" maxlength="30" value="<?= e($edit['name'] ?? '') ?>" placeholder="S1" <?= $canEdit ? 'required' : 'disabled' ?>>
            </div>
            <div class="col-6">
              <label class="form-label" for="c_level">Level</label>
              <select class="form-select" id="c_level" name="level" <?= $canEdit ? '' : 'disabled' ?>>
                <option value="O-Level" <?= ($edit['level'] ?? 'O-Level') === 'O-Level' ? 'selected' : '' ?>>O-Level</option>
                <option value="A-Level" <?= ($edit['level'] ?? '') === 'A-Level' ? 'selected' : '' ?>>A-Level</option>
              </select>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="c_order">Order</label>
              <input class="form-control" id="c_order" name="order_index" type="number" value="<?= (int)($edit['order_index'] ?? 0) ?>" <?= $canEdit ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" id="c_active" name="is_active" type="checkbox" <?= !$edit || (int)$edit['is_active'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                <label class="form-check-label" for="c_active">Active</label>
              </div>
            </div>
          </div>
<?php if ($canEdit): ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create class' ?></button>
<?php if ($edit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('setup/classes') ?>">Cancel</a>
<?php endif; ?>
          </div>
<?php endif; ?>
        </form>
<?php endif; ?>
      </div>
    </div>

<?php if ($current): ?>
    <div class="card app-card">
      <div class="card-header">
        Streams in <?= e($current['name']) ?>
<?php if ($canEdit): ?>
        <a class="float-end small" href="<?= url('setup/classes') ?>">Clear</a>
<?php endif; ?>
      </div>
      <div class="card-body">
<?php if ($canEdit): ?>
        <form method="post" action="<?= url('setup/streams/save') ?>" class="mb-3 pb-3 border-bottom">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)($editStream['id'] ?? 0) ?>">
          <input type="hidden" name="class_id" value="<?= (int)$current['id'] ?>">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small" for="s_name">Stream name</label>
              <input class="form-control form-control-sm" id="s_name" name="name" maxlength="40" value="<?= e($editStream['name'] ?? '') ?>" placeholder="East" required>
            </div>
            <div class="col-6">
              <label class="form-label small" for="s_capacity">Capacity</label>
              <input class="form-control form-control-sm" id="s_capacity" name="capacity" type="number" min="1" max="500" value="<?= (int)($editStream['capacity'] ?? 50) ?>" required>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small" for="s_room">Room</label>
              <input class="form-control form-control-sm" id="s_room" name="room_name" maxlength="60" value="<?= e($editStream['room_name'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label small" for="s_teacher">Class teacher</label>
              <select class="form-select form-select-sm" id="s_teacher" name="class_teacher_id">
                <option value="">— None —</option>
<?php foreach ($staff as $member): ?>
                <option value="<?= (int)$member['id'] ?>" <?= (int)($editStream['class_teacher_id'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['full_name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="form-check">
              <input class="form-check-input" id="s_active" name="is_active" type="checkbox" <?= !$editStream || (int)$editStream['is_active'] ? 'checked' : '' ?>>
              <label class="form-check-label small" for="s_active">Active</label>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" type="submit"><?= $editStream ? 'Save stream' : 'Add stream' ?></button>
<?php if ($editStream): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('setup/classes', ['class_id' => (int)$current['id']]) ?>">Cancel</a>
<?php endif; ?>
          </div>
        </form>
<?php endif; ?>
<?php if (!$streams): ?>
        <p class="text-muted small mb-0">No streams in this class yet.</p>
<?php else: ?>
        <ul class="list-unstyled mb-0">
<?php foreach ($streams as $stream): ?>
          <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <div>
              <strong><?= e($stream['name']) ?></strong>
              <span class="text-muted small">&middot; <?= (int)$stream['student_count'] ?>/<?= (int)$stream['capacity'] ?> students<?= $stream['room_name'] ? ' &middot; ' . e($stream['room_name']) : '' ?></span>
<?php if ($stream['class_teacher_name']): ?>
              <div class="small text-muted">Teacher: <?= e($stream['class_teacher_name']) ?></div>
<?php endif; ?>
            </div>
            <div class="text-nowrap">
<?php if (!(int)$stream['is_active']): ?>
              <span class="badge text-bg-secondary">Inactive</span>
<?php endif; ?>
<?php if ($canEdit): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= url('setup/classes', ['class_id' => (int)$current['id'], 'edit_stream' => (int)$stream['id']]) ?>">Edit</a>
              <form method="post" action="<?= url('setup/streams/toggle') ?>" class="d-inline" data-confirm="<?= (int)$stream['is_active'] ? 'Deactivate' : 'Reactivate' ?> stream <?= e($stream['name']) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$stream['id'] ?>">
                <button class="btn btn-sm btn-outline-warning" type="submit"><?= (int)$stream['is_active'] ? 'Deactivate' : 'Activate' ?></button>
              </form>
<?php endif; ?>
            </div>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">All classes</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead>
              <tr>
                <th>Class</th>
                <th>Level</th>
                <th>Streams</th>
                <th>Students</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$classes): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No classes yet.</td></tr>
<?php endif; ?>
<?php foreach ($classes as $class): ?>
              <tr>
                <td class="fw-semibold"><?= e($class['name']) ?></td>
                <td><span class="badge text-bg-light"><?= e($class['level']) ?></span></td>
                <td><?= (int)$class['stream_count'] ?></td>
                <td><?= number_format((int)$class['student_count']) ?></td>
                <td>
<?php if ((int)$class['is_active']): ?>
                  <span class="badge text-bg-success">Active</span>
<?php else: ?>
                  <span class="badge text-bg-secondary">Inactive</span>
<?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-secondary" href="<?= url('setup/classes', ['class_id' => (int)$class['id']]) ?>">Streams</a>
<?php if ($canEdit): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('setup/classes', ['edit' => (int)$class['id']]) ?>">Edit</a>
                  <form method="post" action="<?= url('setup/classes/toggle') ?>" class="d-inline" data-confirm="<?= (int)$class['is_active'] ? 'Deactivate' : 'Reactivate' ?> <?= e($class['name']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$class['id'] ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit"><?= (int)$class['is_active'] ? 'Deactivate' : 'Activate' ?></button>
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
