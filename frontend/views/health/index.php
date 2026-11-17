<div class="page-head"><div><h1 class="h4 mb-0">Health &amp; student welfare</h1><p class="text-muted small mb-0">Restricted medical profiles and sickbay visits · <?= $canConfidential ? 'confidential records visible' : 'confidential profiles hidden' ?></p></div></div>

<div class="row g-3">
<?php if ($canEdit): ?>
  <div class="col-xl-5">
    <div class="card app-card">
      <div class="card-header">Create or update medical profile</div>
      <div class="card-body">
        <form method="post" action="<?= url('health/medical/save') ?>">
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label">Student</label><select class="form-select" name="student_id" required><option value="">Choose…</option>
<?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?>
          </select></div>
          <div class="mb-3"><label class="form-label">Blood group</label><input class="form-control" name="blood_group" maxlength="10" placeholder="e.g. O+"></div>
          <div class="mb-3"><label class="form-label">Allergies</label><textarea class="form-control" name="allergies" rows="2" maxlength="10000"></textarea></div>
          <div class="mb-3"><label class="form-label">Chronic conditions</label><textarea class="form-control" name="chronic_conditions" rows="2" maxlength="10000"></textarea></div>
          <div class="mb-3"><label class="form-label">Current medication</label><textarea class="form-control" name="current_medication" rows="2" maxlength="10000"></textarea></div>
          <div class="row g-2">
            <div class="col-5"><label class="form-label">Disability status</label><select class="form-select" name="disability_status"><?php foreach (['none','physical','visual','hearing','intellectual','multiple'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
            <div class="col"><label class="form-label">Disability notes</label><input class="form-control" name="disability_notes" maxlength="255"></div>
          </div>
          <div class="mt-3"><label class="form-label">Restricted note</label><textarea class="form-control" name="restricted_note" rows="2" maxlength="10000"></textarea></div>
          <div class="form-check my-3"><input class="form-check-input" type="checkbox" name="is_confidential" id="health_confidential" checked><label class="form-check-label" for="health_confidential">Mark profile confidential</label></div>
          <button class="btn btn-primary">Save medical profile</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>
  <div class="<?= $canEdit ? 'col-xl-7' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Medical profiles</div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Student</th><th>Blood group</th><th>Allergies / conditions</th><th>Access</th><th class="text-end">Referral</th></tr></thead><tbody>
<?php if (!$medicalRecords): ?><tr><td colspan="5" class="text-center text-muted py-4">No visible medical profiles.</td></tr><?php endif; ?>
<?php foreach ($medicalRecords as $record): ?>
        <tr><td><?= e($record['last_name'] . ' ' . $record['first_name']) ?><div class="small text-muted"><?= e($record['admission_number']) ?></div></td>
          <td><?= e($record['blood_group'] ?? '—') ?></td>
          <td><?= e($record['allergies'] ?? '') ?><?php if (!empty($record['chronic_conditions'])): ?><div class="small text-muted"><?= e($record['chronic_conditions']) ?></div><?php endif; ?></td>
          <td><?= (int)($record['is_confidential'] ?? 0) ? '<span class="badge text-bg-warning">Confidential</span>' : '<span class="badge text-bg-light">Restricted</span>' ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('health/referral', ['student_id' => (int)$record['student_id']]) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Referral</a>
          </td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card"><div class="card-header">Record sickbay visit</div><div class="card-body">
      <form method="post" action="<?= url('health/sickbay/record') ?>">
        <?= csrf_field() ?>
        <div class="mb-2"><label class="form-label">Student</label><select class="form-select" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
        <div class="row g-2"><div class="col"><label class="form-label">Date</label><input class="form-control" type="date" name="visit_date" value="<?= date('Y-m-d') ?>" required></div><div class="col"><label class="form-label">Time in</label><input class="form-control" type="time" name="time_in" value="<?= date('H:i') ?>" required></div></div>
        <div class="mt-2"><label class="form-label">Symptoms</label><textarea class="form-control" name="symptoms" rows="2" maxlength="20000"></textarea></div>
        <div class="row g-2 mt-1"><div class="col"><label class="form-label">Temperature °C</label><input class="form-control" type="number" step="0.1" min="25" max="50" name="temperature_c"></div><div class="col"><label class="form-label">Diagnosis</label><input class="form-control" name="diagnosis" maxlength="500"></div></div>
        <div class="mt-2"><label class="form-label">Treatment given</label><textarea class="form-control" name="treatment_given" rows="2" maxlength="20000"></textarea></div>
        <div class="row g-2 mt-1"><div class="col"><label class="form-label">Referral</label><select class="form-select" name="referred_to"><?php foreach (['none','hospital','specialist','parent','clinic'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div><div class="col"><label class="form-label">Visit status</label><select class="form-select" name="status"><?php foreach (['in_progress','discharged','referred','admitted'] as $value): ?><option value="<?= $value ?>" <?= $value === 'discharged' ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($value))) ?></option><?php endforeach; ?></select></div></div>
        <div class="form-check my-3"><input class="form-check-input" type="checkbox" name="parent_informed" id="health_parent"><label class="form-check-label" for="health_parent">Parent informed</label></div>
        <button class="btn btn-primary">Record visit</button>
      </form>
    </div></div>
  </div>
<?php endif; ?>
  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card"><div class="card-header">Recent sickbay visits</div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Student / date</th><th>Symptoms / diagnosis</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
<?php if (!$visits): ?><tr><td colspan="4" class="text-center text-muted py-4">No sickbay visits recorded.</td></tr><?php endif; ?>
<?php foreach ($visits as $visit): ?>
        <tr><td><?= e($visit['last_name'] . ' ' . $visit['first_name']) ?><div class="small text-muted"><?= e($visit['visit_date'] . ' ' . substr($visit['time_in'], 0, 5)) ?></div></td>
          <td><?= e($visit['symptoms'] ?? '') ?><?php if (!empty($visit['diagnosis'])): ?><div class="small text-muted"><?= e($visit['diagnosis']) ?></div><?php endif; ?></td>
          <td><?= e($visit['treatment_given'] ?? '—') ?></td><td><?= e(str_replace('_', ' ', $visit['status'])) ?></td></tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
