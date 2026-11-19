<?php /* Parents & guardians: contact records and the students linked to each one. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Parents &amp; guardians</h1>
    <p class="text-muted small mb-0">Contacts linked to students for SMS, portal and billing</p>
  </div>
  <form method="get" action="<?= url('guardians') ?>" class="d-flex gap-2">
    <input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Name or phone…">
    <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
  </form>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit guardian' : 'New guardian' ?></div>
      <div class="card-body">
        <form method="post" action="<?= url($edit ? 'guardians/update' : 'guardians/store') ?>">
          <?= csrf_field() ?>
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
          <div class="form-section"><i class="bi bi-person-badge"></i> Personal details</div>
          <div class="form-section"><i class="bi bi-telephone"></i> Contact details</div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="g_first">First name</label>
              <input class="form-control" id="g_first" name="first_name" maxlength="60" value="<?= e($edit['first_name'] ?? '') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label" for="g_last">Last name</label>
              <input class="form-control" id="g_last" name="last_name" maxlength="60" value="<?= e($edit['last_name'] ?? '') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label" for="g_rel">Relationship</label>
              <select class="form-select" id="g_rel" name="relationship">
<?php foreach (['father' => 'Father', 'mother' => 'Mother', 'guardian' => 'Guardian', 'sponsor' => 'Sponsor', 'relative' => 'Relative', 'other' => 'Other'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($edit['relationship'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label" for="g_phone">Phone</label>
              <input class="form-control" id="g_phone" name="phone" maxlength="40" value="<?= e($edit['phone'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label" for="g_phone_alt">Alt. phone</label>
              <input class="form-control" id="g_phone_alt" name="phone_alt" maxlength="40" value="<?= e($edit['phone_alt'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label" for="g_email">Email</label>
              <input class="form-control" id="g_email" name="email" type="email" maxlength="120" value="<?= e($edit['email'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label" for="g_occupation">Occupation</label>
              <input class="form-control" id="g_occupation" name="occupation" maxlength="120" value="<?= e($edit['occupation'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label" for="g_district">District</label>
              <input class="form-control" id="g_district" name="district" maxlength="80" value="<?= e($edit['district'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label" for="g_address">Address</label>
              <input class="form-control" id="g_address" name="address" maxlength="255" value="<?= e($edit['address'] ?? '') ?>">
            </div>
            <div class="col-12 d-flex gap-4">
              <div class="form-check">
                <input class="form-check-input" id="g_primary" name="is_primary" type="checkbox" <?= (int)($edit['is_primary'] ?? 0) ? 'checked' : '' ?>>
                <label class="form-check-label" for="g_primary">Primary contact</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" id="g_sms" name="receive_sms" type="checkbox" <?= !isset($edit) || (int)$edit['receive_sms'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="g_sms">Receive SMS</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" id="g_mail" name="receive_email" type="checkbox" <?= !isset($edit) || (int)$edit['receive_email'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="g_mail">Receive email</label>
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add guardian' ?></button>
<?php if ($edit): ?>
            <a class="btn btn-outline-secondary" href="<?= url('guardians') ?>">Cancel</a>
<?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">All guardians</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Email</th><th>Students</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
<?php if (!$guardians): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No guardians found.</td></tr>
<?php endif; ?>
<?php foreach ($guardians as $row): ?>
              <tr>
                <td class="fw-semibold"><?= e($row['first_name'] . ' ' . $row['last_name']) ?><?= (int)$row['is_primary'] ? ' <span class="badge text-bg-primary">Primary</span>' : '' ?></td>
                <td><?= e(ucfirst($row['relationship'])) ?></td>
                <td><?= e($row['phone'] ?: '—') ?></td>
                <td class="small"><?= e($row['email'] ?: '—') ?></td>
                <td>
<?php foreach (Guardian::studentsOf((int)$row['id']) as $child): ?>
                  <a class="d-block small text-decoration-none" href="<?= url('students/profile', ['id' => (int)$child['id']]) ?>">
                    <?= e($child['admission_number'] . ' — ' . $child['last_name'] . ' ' . $child['first_name']) ?>
                  </a>
<?php endforeach; ?>
                  <?= (int)$row['student_count'] === 0 ? '<span class="text-muted small">Not linked yet</span>' : '' ?>
                </td>
                <td class="text-end text-nowrap">
<?php if ($canEdit): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('guardians', ['edit' => (int)$row['id']]) ?>">Edit</a>
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
