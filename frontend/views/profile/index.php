<div class="page-head">
  <div>
    <h1 class="h4 mb-0">My account</h1>
    <p class="text-muted small mb-0">Your profile as registered by the school administrator.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <!-- Basic profile -->
    <div class="card app-card mb-3">
      <div class="card-header">Personal information</div>
      <div class="card-body">
        <form method="post" action="<?= url('profile/update') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="p_username">Username</label>
              <input class="form-control" id="p_username" value="<?= e($profile['username'] ?? '') ?>" disabled>
              <div class="form-text">Username cannot be changed here.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_role">Role</label>
              <input class="form-control" id="p_role" value="<?= e($profile['role_name'] ?? '-') ?> &middot; <?= ucfirst($profile['user_type'] ?? '-') ?>" disabled>
            </div>
            <div class="col-12">
              <label class="form-label" for="p_full_name">Full name <span class="text-danger">*</span></label>
              <input class="form-control" id="p_full_name" name="full_name" value="<?= e($profile['full_name'] ?? '') ?>" maxlength="140" required>
              <div class="invalid-feedback" data-error-for="full_name"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_email">Email</label>
              <input class="form-control" id="p_email" name="email" type="email" value="<?= e($profile['email'] ?? '') ?>" maxlength="120">
              <div class="invalid-feedback" data-error-for="email"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="p_phone">Phone</label>
              <input class="form-control" id="p_phone" name="phone" value="<?= e($profile['phone'] ?? '') ?>" maxlength="40">
              <div class="invalid-feedback" data-error-for="phone"></div>
            </div>
          </div>
          <div class="mt-4">
            <button class="btn btn-primary" type="submit">Save changes</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($profile['student_details'])): ?>
    <!-- Student profile -->
    <div class="card app-card mb-3">
      <div class="card-header"><i class="bi bi-person-vcard me-1"></i> Student record</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted">Admission no.</label>
            <p class="mb-0 fw-bold"><?= e($profile['student_details']['admission_number'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Class / Stream</label>
            <p class="mb-0"><?= e($profile['student_details']['class_name'] ?? '-') ?> / <?= e($profile['student_details']['stream_name'] ?? '—') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">House</label>
            <p class="mb-0"><?= e($profile['student_details']['house_name'] ?? '—') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Student type</label>
            <p class="mb-0"><?= ucfirst($profile['student_details']['student_type'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Status</label>
            <p class="mb-0"><?= ucfirst(str_replace('_', ' ', $profile['student_details']['status'] ?? '-')) ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Academic year</label>
            <p class="mb-0"><?= e($profile['student_details']['academic_year'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Date of birth</label>
            <p class="mb-0"><?= !empty($profile['student_details']['date_of_birth']) ? e(date('d M Y', strtotime($profile['student_details']['date_of_birth']))) : '-' ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Gender</label>
            <p class="mb-0"><?= ucfirst($profile['student_details']['gender'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Nationality</label>
            <p class="mb-0"><?= e($profile['student_details']['nationality'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Previous school</label>
            <p class="mb-0"><?= e($profile['student_details']['previous_school'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Admission date</label>
            <p class="mb-0"><?= !empty($profile['student_details']['admission_date']) ? e(date('d M Y', strtotime($profile['student_details']['admission_date']))) : '-' ?></p>
          </div>
          <?php if (!empty($profile['student_details']['photo_path'])): ?>
          <div class="col-12">
            <label class="form-label small text-muted">Photo</label>
            <img src="<?= e(asset($profile['student_details']['photo_path'])) ?>" alt="Photo" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($profile['staff_details'])): ?>
    <!-- Staff profile -->
    <div class="card app-card mb-3">
      <div class="card-header"><i class="bi bi-person-badge me-1"></i> Staff record</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted">Staff no.</label>
            <p class="mb-0 fw-bold"><?= e($profile['staff_details']['staff_number'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Position</label>
            <p class="mb-0"><?= e($profile['staff_details']['position'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Department</label>
            <p class="mb-0"><?= e($profile['staff_details']['department_name'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Employment type</label>
            <p class="mb-0"><?= ucfirst(str_replace('_', ' ', $profile['staff_details']['employment_type'] ?? '-')) ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Employment status</label>
            <p class="mb-0"><?= ucfirst(str_replace('_', ' ', $profile['staff_details']['employment_status'] ?? '-')) ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Date of appointment</label>
            <p class="mb-0"><?= !empty($profile['staff_details']['date_of_appointment']) ? e(date('d M Y', strtotime($profile['staff_details']['date_of_appointment']))) : '-' ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Teacher reg. no.</label>
            <p class="mb-0"><?= e($profile['staff_details']['teacher_registration_number'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">NSSF number</label>
            <p class="mb-0"><?= e($profile['staff_details']['nssf_number'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">URA TIN</label>
            <p class="mb-0"><?= e($profile['staff_details']['ura_tin'] ?? '-') ?></p>
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Emergency contact</label>
            <p class="mb-0"><?= e($profile['staff_details']['emergency_contact_name'] ?? '-') ?> &mdash; <?= e($profile['staff_details']['emergency_contact_phone'] ?? '-') ?></p>
          </div>
          <?php if (!empty($profile['staff_details']['photo_path'])): ?>
          <div class="col-12">
            <label class="form-label small text-muted">Photo</label>
            <img src="<?= e(asset($profile['staff_details']['photo_path'])) ?>" alt="Photo" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($profile['guardian_details'])): ?>
    <!-- Parent/Guardian profile -->
    <div class="card app-card mb-3">
      <div class="card-header"><i class="bi bi-people-fill me-1"></i> Parent / Guardian record</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small text-muted">Relationship</label>
            <p class="mb-0 fw-bold"><?= ucfirst($profile['guardian_details']['relationship'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Occupation</label>
            <p class="mb-0"><?= e($profile['guardian_details']['occupation'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">Address</label>
            <p class="mb-0"><?= e($profile['guardian_details']['address'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">District</label>
            <p class="mb-0"><?= e($profile['guardian_details']['district'] ?? '-') ?></p>
          </div>
          <div class="col-md-4">
            <label class="form-label small text-muted">NIN</label>
            <p class="mb-0"><?= e($profile['guardian_details']['nin'] ?? '-') ?></p>
          </div>
        </div>
      </div>
    </div>

    <?php if ($profile['linked_students']): ?>
    <div class="card app-card">
      <div class="card-header">Linked students</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Admission no.</th>
                <th>Name</th>
                <th>Class / Stream</th>
                <th>Relationship</th>
                <th class="text-end">Role</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($profile['linked_students'] as $ls): ?>
              <tr>
                <td><?= e($ls['admission_number']) ?></td>
                <td><?= e($ls['first_name'] . ' ' . $ls['last_name']) ?></td>
                <td><?= e($ls['class_name'] ?? '-') ?> / <?= e($ls['stream_name'] ?? '—') ?></td>
                <td><?= ucfirst($ls['link_relationship'] ?? 'guardian') ?></td>
                <td class="text-end">
                  <span class="badge text-bg-<?= (int)$ls['is_fee_payer'] === 1 ? 'success' : 'secondary' ?>">
                    <?= (int)$ls['is_fee_payer'] === 1 ? 'Fee payer' : 'Guardian' ?>
                  </span>
                  <?php if ((int)$ls['is_primary'] === 1): ?> <span class="badge text-bg-primary ms-1">Primary</span><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- School info -->
    <div class="card app-card">
      <div class="card-header">School information</div>
      <div class="card-body small text-muted">
        <div class="mb-2"><strong>School:</strong> <?= e($profile['school_name']) ?></div>
        <div class="mb-2"><strong>Address:</strong> <?= e($profile['school_address']) ?></div>
        <div class="mb-2"><strong>Phone:</strong> <?= e($profile['school_phone']) ?></div>
        <div><strong>Email:</strong> <?= e($profile['school_email']) ?></div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card app-card mb-3">
      <div class="card-header">Security</div>
      <div class="card-body d-grid gap-2">
        <a class="btn btn-outline-primary" href="<?= url('change-password') ?>"><i class="bi bi-key me-1"></i> Change password</a>
        <a class="btn btn-outline-secondary" href="<?= url('security/2fa-setup') ?>"><i class="bi bi-shield-lock me-1"></i> Two-factor authentication</a>
      </div>
    </div>
    <div class="card app-card">
      <div class="card-header">Account details</div>
      <div class="card-body small text-muted">
        <div class="mb-2"><strong>Account type:</strong> <?= e($profile['user_type'] ?? '-') ?></div>
        <div class="mb-2"><strong>Last login:</strong> <?= !empty($profile['last_login_at']) ? e(date('d M Y, H:i', strtotime($profile['last_login_at']))) : 'Never' ?></div>
        <div><strong>Status:</strong> <?= ((int)($profile['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></div>
      </div>
    </div>
  </div>
</div>
