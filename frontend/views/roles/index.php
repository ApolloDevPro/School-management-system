<?php /* Roles and the permission matrix that drives route access. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Roles &amp; permissions</h1>
    <p class="text-muted small mb-0">Control what each role can view, add, edit, approve, print and delete</p>
  </div>
<?php if ($roles): ?>
  <form method="get" action="<?= BASE_URL ?>/index.php" class="d-flex gap-2">
    <input type="hidden" name="r" value="roles">
    <select class="form-select w-auto" name="id" data-submit-on-change aria-label="Choose role">
<?php foreach ($roles as $role): ?>
      <option value="<?= (int)$role['id'] ?>" <?= $current && (int)$current['id'] === (int)$role['id'] ? 'selected' : '' ?>>
        <?= e($role['name']) ?>
      </option>
<?php endforeach; ?>
    </select>
  </form>
<?php endif; ?>
</div>

<?php if (!$current): ?>
<div class="card app-card">
  <div class="card-body text-muted">No roles found.</div>
</div>
<?php else: ?>
<form method="post" action="<?= url('roles/save') ?>" data-ajax>
  <?= csrf_field() ?>
  <input type="hidden" name="role_id" value="<?= (int)$current['id'] ?>">

  <div class="card app-card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
      <span class="fw-semibold"><?= e($current['name']) ?></span>
      <span class="text-muted small"><?= e($current['description'] ?: '') ?></span>
    </div>
    <div class="form-section"><i class="bi bi-shield-lock"></i> Permission matrix</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Module</th>
            <th class="text-center permission-col">View</th>
            <th class="text-center permission-col">Add</th>
            <th class="text-center permission-col">Edit</th>
            <th class="text-center permission-col">Approve</th>
            <th class="text-center permission-col">Print</th>
            <th class="text-center permission-col">Delete</th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($permissions as $permission): ?>
<?php   $flags = $matrix[(int)$permission['id']] ?? []; ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($permission['name']) ?></div>
              <div class="small text-muted"><?= e($permission['description'] ?: '') ?></div>
            </td>
<?php   foreach (['can_view', 'can_add', 'can_edit', 'can_approve', 'can_print', 'can_delete'] as $flag): ?>
            <td class="text-center">
              <input type="hidden" name="perm[<?= (int)$permission['id'] ?>][<?= $flag ?>]" value="0">
              <input class="form-check-input" type="checkbox"
                     name="perm[<?= (int)$permission['id'] ?>][<?= $flag ?>]" value="1"
                     <?= !empty($flags[$flag]) ? 'checked' : '' ?>>
            </td>
<?php   endforeach; ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center">
    <a class="btn btn-outline-secondary" href="<?= url('roles', ['id' => (int)$current['id']]) ?>">Reset changes</a>
    <button class="btn btn-primary" type="submit">Save permissions</button>
  </div>
</form>
<?php endif; ?>
