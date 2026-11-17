<?php
$catColor = fn(string $c): string => match($c) { 'academic'=>'primary','financial'=>'warning','event'=>'info','emergency'=>'danger','health'=>'success', default=>'secondary' };
?>
<div class="page-head">
  <div><h1 class="h4 mb-0">Communication</h1><p class="text-muted small mb-0">Post announcements and notices to staff, students and parents.</p></div>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">New announcement</div>
      <div class="card-body">
        <form method="post" action="<?= url('communication/store') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input class="form-control" name="title" maxlength="255" required>
            <span class="invalid-feedback d-block" data-error-for="title"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Body <span class="text-danger">*</span></label>
            <textarea class="form-control" name="body" rows="4" required></textarea>
            <span class="invalid-feedback d-block" data-error-for="body"></span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Category</label>
              <select class="form-select" name="category">
                <option value="general">General</option>
                <option value="academic">Academic</option>
                <option value="financial">Financial</option>
                <option value="event">Event</option>
                <option value="emergency">Emergency</option>
                <option value="health">Health</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Audience</label>
              <select class="form-select" name="audience">
                <option value="all">Everyone</option>
                <option value="students">Students</option>
                <option value="parents">Parents</option>
                <option value="staff">Staff</option>
                <option value="teachers">Teachers</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Save as draft</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Announcements <span class="badge text-bg-light"><?= count($announcements) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Title</th><th>Category</th><th>Audience</th><th>Created</th><th>Status</th><?php if ($canApprove): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$announcements): ?><tr><td colspan="<?= $canApprove ? 6 : 5 ?>" class="text-center text-muted py-4">No announcements yet.</td></tr><?php endif; ?>
            <?php foreach ($announcements as $ann): ?>
            <tr>
              <td><strong><?= e($ann['title']) ?></strong></td>
              <td><span class="badge text-bg-<?= $catColor($ann['category']) ?>"><?= e($ann['category']) ?></span></td>
              <td><?= e($ann['audience']) ?></td>
              <td class="small"><?= e(date('d M Y', strtotime($ann['created_at']))) ?></td>
              <td><span class="badge text-bg-<?= $ann['status'] === 'published' ? 'success' : ($ann['status'] === 'archived' ? 'light text-dark' : 'secondary') ?>"><?= e($ann['status']) ?></span></td>
              <?php if ($canApprove): ?>
              <td>
                <form method="post" action="<?= url('communication/publish') ?>" data-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                    <option value="draft"     <?= $ann['status'] === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $ann['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="archived"  <?= $ann['status'] === 'archived'  ? 'selected' : '' ?>>Archived</option>
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
