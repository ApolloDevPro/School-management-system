<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Alumni</h1>
    <p class="text-muted small mb-0">Former students after completing S4 or S6</p>
  </div>
  <form method="get" action="<?= url('students/alumni') ?>" class="d-flex gap-2">
    <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Search alumni…">
    <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
  </form>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit alumni record' : 'Add alumni record' ?></div>
      <div class="card-body">
<?php if ($edit): ?>
        <form method="post" action="<?= url('students/alumni/update') ?>" class="mb-3 pb-3 border-bottom">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">Phone</label>
              <input class="form-control form-control-sm" name="phone" value="<?= e($edit['phone'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label small">Email</label>
              <input class="form-control form-control-sm" name="email" type="email" value="<?= e($edit['email'] ?? '') ?>">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">Occupation</label>
              <input class="form-control form-control-sm" name="occupation" value="<?= e($edit['occupation'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label small">Further education</label>
              <input class="form-control form-control-sm" name="further_education" value="<?= e($edit['further_education'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label small">Address</label>
            <input class="form-control form-control-sm" name="current_address" value="<?= e($edit['current_address'] ?? '') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label small">Achievements</label>
            <textarea class="form-control form-control-sm" name="achievements" rows="2"><?= e($edit['achievements'] ?? '') ?></textarea>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" id="a_contact" name="is_contactable" type="checkbox" <?= (int)$edit['is_contactable'] ? 'checked' : '' ?>>
            <label class="form-check-label small" for="a_contact">Contactable</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" id="a_test" name="testimonial_issued" type="checkbox">
            <label class="form-check-label small" for="a_test">Issue testimonial today</label>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary" type="submit">Save</button>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('students/alumni') ?>">Cancel</a>
          </div>
        </form>
<?php endif; ?>

        <form method="post" action="<?= url('students/alumni/store') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="al_student">Student (completed / graduated)</label>
            <select class="form-select" id="al_student" name="student_id" required>
              <option value="">Choose…</option>
<?php foreach ($graduates as $graduate): ?>
              <option value="<?= (int)$graduate['id'] ?>"><?= e($graduate['admission_number'] . ' — ' . $graduate['last_name'] . ' ' . $graduate['first_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="al_year">Completion year</label>
              <input class="form-control" id="al_year" name="completion_year" type="number" min="1990" max="<?= (int)date('Y') + 1 ?>" value="<?= date('Y') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label" for="al_level">Level</label>
              <select class="form-select" id="al_level" name="level_completed">
                <option value="S4">S4 (UCE)</option>
                <option value="S6">S6 (UACE)</option>
              </select>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="al_phone">Phone</label>
              <input class="form-control" id="al_phone" name="phone">
            </div>
            <div class="col-6">
              <label class="form-label" for="al_email">Email</label>
              <input class="form-control" id="al_email" name="email" type="email">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="al_occupation">Occupation</label>
            <input class="form-control" id="al_occupation" name="occupation">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" id="al_contact" name="is_contactable" type="checkbox" checked>
            <label class="form-check-label" for="al_contact">Contactable</label>
          </div>
          <button class="btn btn-primary" type="submit">Add alumni</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Alumni register</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student</th><th>Completed</th><th>Level</th><th>Contact</th><th>Occupation</th><th>Testimonial</th><th class="text-end"></th></tr></thead>
            <tbody>
<?php if (!$alumni): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No alumni records yet.</td></tr>
<?php endif; ?>
<?php foreach ($alumni as $row): ?>
              <tr>
                <td>
                  <span class="fw-semibold"><?= e($row['current_name'] ?: ($row['last_name'] . ' ' . $row['first_name'])) ?></span>
                  <div class="small text-muted"><?= e($row['admission_number']) ?></div>
                </td>
                <td><?= (int)$row['completion_year'] ?></td>
                <td><span class="badge text-bg-light"><?= e($row['level_completed']) ?></span></td>
                <td class="small"><?= e($row['phone'] ?: $row['email'] ?: '—') ?></td>
                <td class="small"><?= e($row['occupation'] ?: '—') ?></td>
                <td class="small"><?= $row['testimonial_issued_at'] ? e(date('d M Y', strtotime($row['testimonial_issued_at']))) : '—' ?></td>
                <td class="text-end">
<?php if ($canEdit): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('students/alumni', ['edit' => (int)$row['id']]) ?>">Edit</a>
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
