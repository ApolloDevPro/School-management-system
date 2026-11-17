<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Student discipline</h1>
    <p class="text-muted small mb-0">Incidents, sanctions and follow-up</p>
  </div>
  <form method="get" action="<?= url('students/discipline') ?>" class="d-flex gap-2">
    <select class="form-select form-select-sm" name="status" data-submit-on-change>
      <option value="">All statuses</option>
<?php foreach (['open' => 'Open', 'under_review' => 'Under review', 'follow_up' => 'Follow-up', 'closed' => 'Closed'] as $value => $label): ?>
      <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
    </select>
  </form>
</div>

<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-lg-4">
    <div class="card app-card">
      <div class="card-header"><?= $edit ? 'Edit incident #' . (int)$edit['id'] : 'Record an incident' ?></div>
      <div class="card-body">
<?php if ($edit): ?>
        <div class="alert alert-secondary small py-2">Editing closes or updates an existing incident below. <a href="<?= url('students/discipline') ?>">Cancel</a></div>
<?php endif; ?>
        <form method="post" action="<?= url('students/discipline/store') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="d_student">Student</label>
            <select class="form-select" id="d_student" name="student_id" required>
              <option value="">Choose…</option>
<?php foreach ($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>" <?= $edit && (int)$edit['student_id'] === (int)$student['id'] ? 'selected' : '' ?>>
                <?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?>
              </option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="d_date">Date</label>
              <input class="form-control" id="d_date" name="incident_date" type="date" value="<?= e($edit['incident_date'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label" for="d_time">Time</label>
              <input class="form-control" id="d_time" name="incident_time" type="time" value="<?= e($edit['incident_time'] ?? '') ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_category">Category</label>
            <select class="form-select" id="d_category" name="category" required>
<?php foreach (['late_coming' => 'Late coming', 'truancy' => 'Truancy', 'disrespect' => 'Disrespect', 'fighting' => 'Fighting', 'theft' => 'Theft', 'vandalism' => 'Vandalism', 'cheating' => 'Cheating', 'substance' => 'Substance abuse', 'uniform' => 'Uniform', 'bullying' => 'Bullying', 'other' => 'Other'] as $value => $label): ?>
              <option value="<?= $value ?>" <?= ($edit['category'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_desc">Description *</label>
            <textarea class="form-control" id="d_desc" name="description" rows="3" required><?= e($edit['description'] ?? '') ?></textarea>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="d_location">Location</label>
              <input class="form-control" id="d_location" name="location" maxlength="100" value="<?= e($edit['location'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label" for="d_witness">Witness (staff)</label>
              <select class="form-select" id="d_witness" name="witness_staff_id">
                <option value="0">— None —</option>
<?php foreach ($staff as $member): ?>
                <option value="<?= (int)$member['id'] ?>" <?= (int)($edit['witness_staff_id'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['full_name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_sanction">Sanction</label>
            <select class="form-select" id="d_sanction" name="sanction">
<?php foreach (['none' => 'None', 'warning' => 'Warning', 'counselling' => 'Counselling', 'community_service' => 'Community service', 'suspension' => 'Suspension', 'expulsion' => 'Expulsion', 'parent_meeting' => 'Parent meeting'] as $value => $label): ?>
              <option value="<?= $value ?>" <?= ($edit['sanction'] ?? 'warning') === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="d_action">Action taken</label>
            <input class="form-control" id="d_action" name="action_taken" maxlength="255" value="<?= e($edit['action_taken'] ?? '') ?>">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" id="d_parent" name="parent_notified" type="checkbox" <?= (int)($edit['parent_notified'] ?? 0) ? 'checked' : '' ?>>
            <label class="form-check-label" for="d_parent">Parent notified</label>
          </div>
          <button class="btn btn-primary" type="submit"><?= $edit ? 'Save incident' : 'Record incident' ?></button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Incidents</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th>Student</th><th>Category</th><th>Sanction</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
<?php if (!$incidents): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No incidents recorded.</td></tr>
<?php endif; ?>
<?php foreach ($incidents as $incident): ?>
              <tr>
                <td class="text-nowrap"><?= e(date('d M Y', strtotime($incident['incident_date']))) ?></td>
                <td>
                  <a class="fw-semibold text-decoration-none" href="<?= url('students/profile', ['id' => (int)$incident['student_id']]) ?>">
                    <?= e($incident['last_name'] . ' ' . $incident['first_name']) ?>
                  </a>
                  <div class="small text-muted"><?= e($incident['admission_number']) ?> · <?= e($incident['class_name'] ?? '') ?></div>
                </td>
                <td><?= e(str_replace('_', ' ', ucfirst($incident['category']))) ?></td>
                <td class="small"><?= e($incident['sanction']) ?></td>
                <td><span class="badge text-bg-light"><?= e($incident['status']) ?></span></td>
                <td class="text-end text-nowrap">
<?php if ($canEdit && $incident['status'] !== 'closed'): ?>
                  <form method="post" action="<?= url('students/discipline/update') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                    <input type="hidden" name="status" value="closed">
                    <input type="hidden" name="action_taken" value="<?= e($incident['action_taken'] ?? '') ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit" data-confirm="Close incident #<?= (int)$incident['id'] ?>?">Close</button>
                  </form>
                  <form method="post" action="<?= url('students/discipline/update') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                    <input type="hidden" name="status" value="follow_up">
                    <input type="hidden" name="action_taken" value="<?= e($incident['action_taken'] ?? '') ?>">
                    <button class="btn btn-sm btn-outline-warning" type="submit">Follow-up</button>
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
