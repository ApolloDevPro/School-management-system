<?php if (!$students || !$years || !$terms): ?>
<div class="alert alert-warning">Academic years, terms and active students are required before registering boarders.</div>
<?php endif; ?>

<div class="page-head">
  <div><h1 class="h4 mb-0">Boarding</h1><p class="text-muted small mb-0">Manage boarding registrations and dormitory assignments.</p></div>
</div>

<div class="row g-3">
<?php if ($canAdd && $students && $years && $terms): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Register boarder</div>
      <div class="card-body">
        <form method="post" action="<?= url('boarding/register') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Student <span class="text-danger">*</span></label>
            <select class="form-select" name="student_id" required>
              <option value="">Choose student…</option>
              <?php foreach ($students as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['admission_number'] . ' — ' . $s['last_name'] . ' ' . $s['first_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="invalid-feedback d-block" data-error-for="student_id"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Academic year <span class="text-danger">*</span></label>
              <select class="form-select" name="academic_year_id" required>
                <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y['id'] ?>" <?= !empty($y['is_active']) ? 'selected' : '' ?>><?= e($y['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Term <span class="text-danger">*</span></label>
              <select class="form-select" name="term_id" required>
                <option value="">Choose…</option>
                <?php foreach ($terms as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="term_id"></span>
            </div>
          </div>
          <?php if ($dormitories): ?>
          <div class="mb-2">
            <label class="form-label">Dormitory</label>
            <select class="form-select" name="dormitory_id">
              <option value="0">Not assigned yet</option>
              <?php foreach ($dormitories as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="mb-2">
            <label class="form-label">Emergency contact</label>
            <input class="form-control" name="emergency_contact" maxlength="160" placeholder="Name and phone number">
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2" maxlength="500"></textarea>
          </div>
          <button class="btn btn-primary" type="submit">Register boarder</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= ($canAdd && $students && $years && $terms) ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Boarding registrations <span class="badge text-bg-light"><?= count($registrations) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead>
            <tr>
              <th>Student</th><th>Year / Term</th><th>Dormitory</th><th>Registered</th><th>Status</th>
              <?php if ($canEdit): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$registrations): ?>
            <tr><td colspan="<?= $canEdit ? 7 : 6 ?>" class="text-center text-muted py-4">No boarding registrations yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($registrations as $reg): ?>
            <?php $statusColor = match($reg['status']) { 'checked_in' => 'success', 'checked_out' => 'secondary', 'cancelled' => 'danger', default => 'primary' }; ?>
            <tr>
              <td><strong><?= e($reg['last_name'] . ' ' . $reg['first_name']) ?></strong><div class="small text-muted"><?= e($reg['admission_number']) ?></div></td>
              <td><?= e($reg['year_name']) ?><div class="small text-muted"><?= e($reg['term_name']) ?></div></td>
              <td><?= e($reg['dormitory_name'] ?? '—') ?></td>
              <td class="small"><?= e(date('d M Y', strtotime($reg['registered_at']))) ?></td>
              <td><span class="badge text-bg-<?= $statusColor ?>"><?= e(str_replace('_', ' ', $reg['status'])) ?></span></td>
              <?php if ($canEdit): ?>
              <td>
                <form method="post" action="<?= url('boarding/status') ?>" data-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$reg['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                    <option value="registered"  <?= $reg['status'] === 'registered'  ? 'selected' : '' ?>>Registered</option>
                    <option value="checked_in"  <?= $reg['status'] === 'checked_in'  ? 'selected' : '' ?>>Checked in</option>
                    <option value="checked_out" <?= $reg['status'] === 'checked_out' ? 'selected' : '' ?>>Checked out</option>
                    <option value="cancelled"   <?= $reg['status'] === 'cancelled'   ? 'selected' : '' ?>>Cancelled</option>
                  </select>
                </form>
              </td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= url('boarding/allocation-letter', ['student_id' => (int)$reg['student_id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Letter</a>
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
