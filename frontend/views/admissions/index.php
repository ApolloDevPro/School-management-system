<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Admissions</h1>
    <p class="text-muted small mb-0">Applications from prospective students</p>
  </div>
  <form method="get" action="<?= url('admissions') ?>" class="d-flex gap-2">
    <select class="form-select form-select-sm" name="status" data-submit-on-change>
      <option value="">All statuses</option>
<?php foreach (['pending' => 'Pending', 'admitted' => 'Admitted', 'waitlisted' => 'Waitlisted', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'] as $value => $label): ?>
      <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
    </select>
  </form>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header">New application</div>
      <div class="card-body">
        <form method="post" action="<?= url('admissions/store') ?>">
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-person-badge"></i> Applicant details</div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" for="ap_first">First name</label>
              <input class="form-control" id="ap_first" name="first_name" maxlength="60" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_middle">Middle name</label>
              <input class="form-control" id="ap_middle" name="middle_name" maxlength="60">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_last">Last name</label>
              <input class="form-control" id="ap_last" name="last_name" maxlength="60" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_gender">Gender</label>
              <select class="form-select" id="ap_gender" name="gender" required>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_dob">Date of birth</label>
              <input class="form-control" id="ap_dob" name="date_of_birth" type="date">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_year">Academic year</label>
              <select class="form-select" id="ap_year" name="academic_year_id" required>
<?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['id'] ?>" <?= (int)$year['is_active'] ? 'selected' : '' ?>><?= e($year['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_class">Applied class</label>
              <select class="form-select" id="ap_class" name="applied_class_id">
                <option value="0">— Any —</option>
<?php foreach ($classes as $class): ?>
                <option value="<?= (int)$class['id'] ?>"><?= e($class['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_source">Source</label>
              <select class="form-select" id="ap_source" name="source">
                <option value="walk_in">Walk-in</option>
                <option value="website">Website</option>
                <option value="transfer">Transfer</option>
                <option value="recommendation">Recommendation</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_prev_school">Previous school</label>
              <input class="form-control" id="ap_prev_school" name="previous_school" maxlength="160">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_prev_result">Previous result</label>
              <input class="form-control" id="ap_prev_result" name="previous_result" maxlength="120">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_guardian">Guardian name</label>
              <input class="form-control" id="ap_guardian" name="guardian_name" maxlength="140">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ap_guardian_phone">Guardian phone</label>
              <input class="form-control" id="ap_guardian_phone" name="guardian_phone" maxlength="40">
            </div>
            <div class="col-12">
              <label class="form-label" for="ap_notes">Notes</label>
              <textarea class="form-control" id="ap_notes" name="notes" rows="2"></textarea>
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Record application</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Applications</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead><tr><th>App. no.</th><th>Applicant</th><th>Class</th><th>Year</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
<?php if (!$applications): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No applications found.</td></tr>
<?php endif; ?>
<?php foreach ($applications as $application): ?>
              <tr>
                <td class="fw-semibold"><?= e($application['application_number']) ?></td>
                <td>
                  <?= e($application['last_name'] . ', ' . $application['first_name']) ?>
                  <div class="small text-muted"><?= e(ucfirst($application['gender'])) ?><?= $application['guardian_phone'] ? ' · ' . e($application['guardian_phone']) : '' ?></div>
                </td>
                <td><?= e($application['applied_class_name'] ?? '—') ?></td>
                <td><?= e($application['year_name']) ?></td>
                <td>
<?php
                  $badge = match ($application['status']) {
                      'admitted' => 'text-bg-success',
                      'pending' => 'text-bg-warning',
                      'waitlisted' => 'text-bg-info',
                      default => 'text-bg-secondary',
                  };
?>
                  <span class="badge <?= $badge ?>"><?= e(ucfirst($application['status'])) ?></span>
                  <?php if ($application['admission_number']): ?><div class="small"><?= e($application['admission_number']) ?></div><?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
<?php if ($canEdit && in_array($application['status'], ['pending', 'waitlisted'], true)): ?>
                  <form method="post" action="<?= url('admissions/decide') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$application['id'] ?>">
                    <input type="hidden" name="decision" value="admitted">
                    <select class="form-select form-select-sm d-inline-block w-auto me-1" name="class_id">
                      <option value="0">Class…</option>
<?php foreach ($classes as $class): ?>
                      <option value="<?= (int)$class['id'] ?>" <?= (int)$application['applied_class_id'] === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option>
<?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-success" type="submit" data-confirm="Admit this applicant? A student record and admission number will be created.">Admit</button>
                  </form>
                  <form method="post" action="<?= url('admissions/decide') ?>" class="d-inline" data-confirm="Reject this application?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$application['id'] ?>">
                    <input type="hidden" name="decision" value="rejected">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                  </form>
                  <form method="post" action="<?= url('admissions/decide') ?>" class="d-inline" data-confirm="Waitlist this application?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$application['id'] ?>">
                    <input type="hidden" name="decision" value="waitlisted">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">Waitlist</button>
                  </form>
<?php endif; ?>
<?php if ($application['student_id']): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= url('students/profile', ['id' => (int)$application['student_id']]) ?>">Profile</a>
                  <?php if ($application['status'] === 'admitted'): ?>
                  <a class="btn btn-sm btn-outline-success" href="<?= url('admissions/letter', ['student_id' => (int)$application['student_id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Letter</a>
                  <?php endif; ?>
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
