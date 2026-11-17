<div class="page-head">
  <div><h1 class="h4 mb-0">Co-curricular activities</h1><p class="text-muted small mb-0">Manage clubs, sports teams and extra-curricular activities.</p></div>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Add club / society</div>
      <div class="card-body">
        <form method="post" action="<?= url('cocurricular/clubs/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Club name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" maxlength="160" required>
            <span class="invalid-feedback d-block" data-error-for="name"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Category</label>
            <select class="form-select" name="club_category">
              <option value="academic">Academic</option>
              <option value="sports">Sports</option>
              <option value="arts">Arts &amp; culture</option>
              <option value="debate">Debate</option>
              <option value="science">Science</option>
              <option value="religious">Religious</option>
              <option value="community">Community service</option>
              <option value="general" selected>General</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="2" maxlength="500"></textarea>
          </div>
          <button class="btn btn-primary" type="submit">Create club</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Clubs &amp; societies <span class="badge text-bg-light"><?= count($clubs) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Name</th><th>Category</th><th>Meeting</th><th>Venue</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$clubs): ?><tr><td colspan="<?= $canEdit ? 6 : 5 ?>" class="text-center text-muted py-4">No clubs registered yet.</td></tr><?php endif; ?>
            <?php foreach ($clubs as $club): ?>
            <tr>
              <td><strong><?= e($club['name']) ?></strong></td>
              <td><?= e(ucfirst($club['club_category'])) ?></td>
              <td><?= e($club['meeting_day'] ?? '—') ?></td>
              <td><?= e($club['meeting_venue'] ?? '—') ?></td>
              <td><span class="badge text-bg-<?= $club['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($club['status']) ?></span></td>
              <?php if ($canEdit): ?>
              <td>
                <form method="post" action="<?= url('cocurricular/clubs/toggle') ?>" data-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$club['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                    <option value="active"   <?= $club['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $club['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  </select>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
