<?php
/* Health and student welfare: restricted profiles, sickbay, medication, incidents and counselling. */
$incidentBadge = static fn(string $s): string => match ($s) {
    'resolved' => 'success', 'escalated' => 'danger', 'investigating' => 'warning',
    'parent_involved' => 'info', default => 'secondary'
};
$welfareBadge = static fn(string $s): string => match ($s) {
    'closed' => 'success', 'supported' => 'info', 'in_progress' => 'warning', default => 'secondary'
};
$studentName = static fn(array $row): string => $row['last_name'] . ' ' . $row['first_name'];
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Health &amp; student welfare</h1>
    <p class="text-muted small mb-0">Restricted medical profiles, sickbay, medication and welfare records · <?= $canConfidential ? 'confidential records visible' : 'confidential records hidden' ?></p>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary stat-with-icon"><div class="stat-icon"><i class="bi bi-capsule"></i></div><div class="stat-body"><div class="stat-label">Medication (30 days)</div><div class="stat-value"><?= (int)($summary['medications_30d'] ?? 0) ?></div><div class="stat-note">Doses administered</div></div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger stat-with-icon"><div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div><div class="stat-body"><div class="stat-label">Open incidents</div><div class="stat-value"><?= (int)($summary['open_incidents'] ?? 0) ?></div><div class="stat-note">Awaiting resolution</div></div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info stat-with-icon"><div class="stat-icon"><i class="bi bi-chat-heart"></i></div><div class="stat-body"><div class="stat-label">Counselling</div><div class="stat-value"><?= (int)($summary['active_counselling'] ?? 0) ?></div><div class="stat-note">Active sessions</div></div></div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-warning stat-with-icon"><div class="stat-icon"><i class="bi bi-house-heart"></i></div><div class="stat-body"><div class="stat-label">Welfare cases</div><div class="stat-value"><?= (int)($summary['open_welfare'] ?? 0) ?></div><div class="stat-note">Open or in progress</div></div></div>
  </div>
</div>

<ul class="nav nav-pills app-pills mb-3" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'profiles' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-profiles" type="button" role="tab">Medical profiles</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'sickbay' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-sickbay" type="button" role="tab">Sickbay</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'medication' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-medication" type="button" role="tab">Medication</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'incidents' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-incidents" type="button" role="tab">Incidents</button></li>
  <li class="nav-item" role="presentation"><button class="nav-link <?= $tab === 'counselling' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-counselling" type="button" role="tab">Counselling &amp; welfare</button></li>
</ul>

<div class="tab-content">

  <!-- Medical profiles ------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'profiles' ? 'show active' : '' ?>" id="tab-profiles" role="tabpanel">
    <div class="row g-3">
<?php if ($canEdit): ?>
      <div class="col-xl-5">
        <div class="card app-card">
          <div class="card-header">Create or update medical profile</div>
          <div class="card-body">
            <form method="post" action="<?= url('health/medical/save') ?>">
              <?= csrf_field() ?>
              <div class="form-section"><i class="bi bi-person-vcard"></i> Learner profile</div>
              <div class="mb-3"><label class="form-label">Student</label><select class="form-select" name="student_id" required><option value="">Choose…</option>
<?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?>
              </select></div>
              <div class="mb-3"><label class="form-label">Blood group</label><input class="form-control" name="blood_group" maxlength="10" placeholder="e.g. O+"></div>
              <div class="mb-3"><label class="form-label">Allergies</label><textarea class="form-control" name="allergies" rows="2" maxlength="10000"></textarea></div>
              <div class="mb-3"><label class="form-label">Chronic conditions</label><textarea class="form-control" name="chronic_conditions" rows="2" maxlength="10000"></textarea></div>
              <div class="mb-3"><label class="form-label">Current medication</label><textarea class="form-control" name="current_medication" rows="2" maxlength="10000"></textarea></div>
              <div class="form-section"><i class="bi bi-heart-pulse"></i> Conditions</div>
              <div class="row g-2">
                <div class="col-5"><label class="form-label">Disability status</label><select class="form-select" name="disability_status"><?php foreach (['none','physical','visual','hearing','intellectual','multiple'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
                <div class="col"><label class="form-label">Disability notes</label><input class="form-control" name="disability_notes" maxlength="255"></div>
              </div>
              <div class="form-section"><i class="bi bi-shield-lock"></i> Access control</div>
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
          <div class="card-header">Medical profiles <span class="badge text-bg-light"><?= count($medicalRecords) ?></span></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student</th><th>Blood group</th><th>Allergies / conditions</th><th>Access</th><th class="text-end">Referral</th></tr></thead><tbody>
<?php if (!$medicalRecords): ?><tr><td colspan="5" class="text-center text-muted py-4">No visible medical profiles.</td></tr><?php endif; ?>
<?php foreach ($medicalRecords as $record): ?>
            <tr><td><?= e($studentName($record)) ?><div class="small text-muted"><?= e($record['admission_number']) ?></div></td>
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
  </div>

  <!-- Sickbay ---------------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'sickbay' ? 'show active' : '' ?>" id="tab-sickbay" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card"><div class="card-header">Record sickbay visit</div><div class="card-body">
          <form method="post" action="<?= url('health/sickbay/record') ?>">
            <?= csrf_field() ?>
            <div class="form-section"><i class="bi bi-clipboard-heart"></i> Visit details</div>
            <div class="mb-2"><label class="form-label">Student</label><select class="form-select" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
            <div class="row g-2"><div class="col"><label class="form-label">Date</label><input class="form-control" type="date" name="visit_date" value="<?= date('Y-m-d') ?>" required></div><div class="col"><label class="form-label">Time in</label><input class="form-control" type="time" name="time_in" value="<?= date('H:i') ?>" required></div></div>
            <div class="mt-2"><label class="form-label">Symptoms</label><textarea class="form-control" name="symptoms" rows="2" maxlength="20000"></textarea></div>
            <div class="row g-2 mt-1"><div class="col"><label class="form-label">Temperature °C</label><input class="form-control" type="number" step="0.1" min="25" max="50" name="temperature_c"></div><div class="col"><label class="form-label">Diagnosis</label><input class="form-control" name="diagnosis" maxlength="500"></div></div>
            <div class="form-section"><i class="bi bi-bandaid"></i> Treatment</div>
            <div class="mt-2"><label class="form-label">Treatment given</label><textarea class="form-control" name="treatment_given" rows="2" maxlength="20000"></textarea></div>
            <div class="row g-2 mt-1"><div class="col"><label class="form-label">Referral</label><select class="form-select" name="referred_to"><?php foreach (['none','hospital','specialist','parent','clinic'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div><div class="col"><label class="form-label">Visit status</label><select class="form-select" name="status"><?php foreach (['in_progress','discharged','referred','admitted'] as $value): ?><option value="<?= $value ?>" <?= $value === 'discharged' ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($value))) ?></option><?php endforeach; ?></select></div></div>
            <div class="form-check my-3"><input class="form-check-input" type="checkbox" name="parent_informed" id="health_parent"><label class="form-check-label" for="health_parent">Parent informed</label></div>
            <button class="btn btn-primary">Record visit</button>
          </form>
        </div></div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card"><div class="card-header">Recent sickbay visits <span class="badge text-bg-light"><?= count($visits) ?></span></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student / date</th><th>Symptoms / diagnosis</th><th>Treatment</th><th>Status</th></tr></thead><tbody>
<?php if (!$visits): ?><tr><td colspan="4" class="text-center text-muted py-4">No sickbay visits recorded.</td></tr><?php endif; ?>
<?php foreach ($visits as $visit): ?>
            <tr><td><?= e($studentName($visit)) ?><div class="small text-muted"><?= e($visit['visit_date'] . ' ' . substr((string)$visit['time_in'], 0, 5)) ?></div></td>
              <td><?= e($visit['symptoms'] ?? '') ?><?php if (!empty($visit['diagnosis'])): ?><div class="small text-muted"><?= e($visit['diagnosis']) ?></div><?php endif; ?></td>
              <td><?= e($visit['treatment_given'] ?? '—') ?></td><td><?= e(str_replace('_', ' ', $visit['status'])) ?></td></tr>
<?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Medication ------------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'medication' ? 'show active' : '' ?>" id="tab-medication" role="tabpanel">
<?php if (!$canConfidential): ?>
    <div class="alert alert-warning border-0 bg-warning bg-opacity-10"><i class="bi bi-shield-lock me-1"></i> Medication records are confidential. Ask an administrator for health edit permission.</div>
<?php else: ?>
    <div class="row g-3">
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Record medication given</div>
          <div class="card-body">
            <form method="post" action="<?= url('health/medication/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">Student <span class="text-danger">*</span></label><select class="form-select" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Medication <span class="text-danger">*</span></label><input class="form-control" name="medication_name" maxlength="160" required></div>
                <div class="col-5"><label class="form-label">Dosage <span class="text-danger">*</span></label><input class="form-control" name="dosage" maxlength="80" placeholder="e.g. 500 mg"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Route</label><select class="form-select" name="route"><?php foreach (['oral','topical','injection','inhalation','eye_drops','ear_drops','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Given at</label><input class="form-control" type="datetime-local" name="administered_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
              </div>
              <div class="mb-2"><label class="form-label">Frequency</label><input class="form-control" name="frequency" maxlength="80" placeholder="e.g. twice daily"></div>
              <div class="mb-2"><label class="form-label">Reason</label><input class="form-control" name="reason" maxlength="255"></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Given on</label><select class="form-select" name="given_by_request"><option value="nurse">Nurse</option><option value="prescription">Prescription</option><option value="parent">Parent request</option><option value="self">Self</option></select></div>
                <div class="col-6"><label class="form-label">Batch no.</label><input class="form-control" name="batch_number" maxlength="60"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Expiry</label><input class="form-control" type="date" name="expiry_date"></div>
                <div class="col-6"><label class="form-label">Reaction</label><input class="form-control" name="reaction_notes" maxlength="255"></div>
              </div>
              <button class="btn btn-primary">Save medication</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="card app-card">
          <div class="card-header">Medication log <span class="badge text-bg-light"><?= count($medications) ?></span></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student</th><th>Medication</th><th>Route / frequency</th><th>Given</th><th>By</th><th>Reaction</th></tr></thead><tbody>
<?php if (!$medications): ?><tr><td colspan="6" class="text-center text-muted py-4">No medication recorded.</td></tr><?php endif; ?>
<?php foreach ($medications as $med): ?>
            <tr><td><?= e($studentName($med)) ?><div class="small text-muted"><?= e($med['admission_number']) ?></div></td>
              <td><strong><?= e($med['medication_name']) ?></strong> <?= e($med['dosage']) ?><?php if ($med['reason']): ?><div class="small text-muted"><?= e($med['reason']) ?></div><?php endif; ?></td>
              <td><?= e(ucwords(str_replace('_', ' ', $med['route']))) ?><?php if ($med['frequency']): ?><div class="small text-muted"><?= e($med['frequency']) ?></div><?php endif; ?></td>
              <td class="small"><?= e(date('d M Y H:i', strtotime($med['administered_at']))) ?></td>
              <td class="small"><?= e($med['administered_by_name'] ?: ucfirst($med['given_by_request'])) ?></td>
              <td class="small text-<?= $med['reaction_notes'] ? 'danger' : 'muted' ?>"><?= e($med['reaction_notes'] ?? '—') ?></td></tr>
<?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
<?php endif; ?>
  </div>

  <!-- Incidents -------------------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'incidents' ? 'show active' : '' ?>" id="tab-incidents" role="tabpanel">
    <div class="row g-3">
<?php if ($canAdd): ?>
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">Log medical incident</div>
          <div class="card-body">
            <form method="post" action="<?= url('health/incidents/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">Student</label><select class="form-select" name="student_id"><option value="0">Whole school / unknown</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="row g-2 mb-2">
                <div class="col-7"><label class="form-label">Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="incident_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="col-5"><label class="form-label">Time</label><input class="form-control" type="time" name="incident_time"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Type</label><select class="form-select" name="incident_type"><?php foreach (['accident','injury','illness','emergency','food_poisoning','allergic_reaction','death','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Location</label><input class="form-control" name="location" maxlength="120"></div>
              </div>
              <div class="mb-2"><label class="form-label">Description <span class="text-danger">*</span></label><textarea class="form-control" name="description" rows="2" required></textarea></div>
              <div class="mb-2"><label class="form-label">Action taken</label><textarea class="form-control" name="action_taken" rows="2"></textarea></div>
              <div class="mb-2"><label class="form-label">Witnesses</label><input class="form-control" name="witnesses" maxlength="255"></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Transport used</label><input class="form-control" name="transport_used" maxlength="120"></div>
                <div class="col-6"><label class="form-label">Cost (UGX)</label><input class="form-control" type="number" min="0" step="0.01" name="incident_cost"></div>
              </div>
              <div class="mb-2"><label class="form-label">Hospital</label><input class="form-control" name="hospital_name" maxlength="160"></div>
              <div class="mb-2"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['reported','investigating','parent_involved','resolved','escalated'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
              <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="parent_informed" id="i_parent"><label class="form-check-label" for="i_parent">Parent informed</label></div>
              <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="referred_to_hospital" id="i_hospital"><label class="form-check-label" for="i_hospital">Referred to hospital</label></div>
              <button class="btn btn-primary">Log incident</button>
            </form>
          </div>
        </div>
      </div>
<?php endif; ?>
      <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
        <div class="card app-card">
          <div class="card-header">Incident register <span class="badge text-bg-light"><?= count($incidents) ?></span></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Date</th><th>Student / type</th><th>Description</th><th>Parent</th><th>Hospital</th><th>Status</th></tr></thead><tbody>
<?php if (!$incidents): ?><tr><td colspan="6" class="text-center text-muted py-4">No incidents recorded.</td></tr><?php endif; ?>
<?php foreach ($incidents as $incident): ?>
            <tr><td class="small text-nowrap"><?= e(date('d M Y', strtotime($incident['incident_date']))) ?><?= $incident['incident_time'] ? '<div class="text-muted">' . e(substr((string)$incident['incident_time'], 0, 5)) . '</div>' : '' ?></td>
              <td class="small"><strong><?= $incident['last_name'] ? e($studentName($incident)) : 'Whole school' ?></strong><div class="text-muted"><?= e(ucwords(str_replace('_', ' ', $incident['incident_type']))) ?></div></td>
              <td class="small"><span class="d-inline-block text-truncate" style="max-width:18rem" title="<?= e($incident['description']) ?>"><?= e($incident['description']) ?></span><?php if ($incident['action_taken']): ?><div class="text-muted"><?= e(mb_substr($incident['action_taken'], 0, 80)) ?></div><?php endif; ?></td>
              <td><?= (int)$incident['parent_informed'] ? '<span class="badge text-bg-success">Yes</span>' : '<span class="badge text-bg-light">No</span>' ?></td>
              <td class="small"><?= (int)$incident['referred_to_hospital'] ? e($incident['hospital_name'] ?: 'Yes') : '—' ?></td>
              <td>
<?php if ($canEdit): ?>
                <form method="post" action="<?= url('health/incidents/status') ?>">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$incident['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
<?php foreach (['reported','investigating','parent_involved','resolved','escalated'] as $value): ?>
                    <option value="<?= $value ?>" <?= $incident['status'] === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option>
<?php endforeach; ?>
                  </select>
                </form>
<?php else: ?>
                <span class="badge text-bg-<?= $incidentBadge($incident['status']) ?>"><?= e(str_replace('_', ' ', $incident['status'])) ?></span>
<?php endif; ?>
              </td></tr>
<?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Counselling and welfare ------------------------------------------------->
  <div class="tab-pane fade <?= $tab === 'counselling' ? 'show active' : '' ?>" id="tab-counselling" role="tabpanel">
<?php if (!$canConfidential): ?>
    <div class="alert alert-warning border-0 bg-warning bg-opacity-10"><i class="bi bi-shield-lock me-1"></i> Counselling and welfare records are confidential. Ask an administrator for health edit permission.</div>
<?php else: ?>
    <div class="row g-3">
      <div class="col-xl-4">
        <div class="card app-card">
          <div class="card-header">New counselling session</div>
          <div class="card-body">
            <form method="post" action="<?= url('health/counselling/save') ?>">
              <?= csrf_field() ?>
              <div class="mb-2"><label class="form-label">Student <span class="text-danger">*</span></label><select class="form-select" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name'] . ' ' . $student['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Date</label><input class="form-control" type="date" name="session_date" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-3"><label class="form-label">Time</label><input class="form-control" type="time" name="session_time"></div>
                <div class="col-3"><label class="form-label">Mins</label><input class="form-control" type="number" min="1" max="480" name="duration_minutes" placeholder="30"></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (['academic','personal','social','emotional','career','behavioural','crisis','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Referred by</label><select class="form-select" name="referral_source"><option value="">—</option><?php foreach (['self','teacher','parent','head_teacher','peer','discipline','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
              </div>
              <div class="mb-2"><label class="form-label">Concerns</label><textarea class="form-control" name="concerns" rows="2"></textarea></div>
              <div class="mb-2"><label class="form-label">Observations</label><textarea class="form-control" name="observations" rows="2"></textarea></div>
              <div class="mb-2"><label class="form-label">Action plan</label><textarea class="form-control" name="action_plan" rows="2"></textarea></div>
              <div class="mb-2"><label class="form-label">Counsellor</label><select class="form-select" name="counsellor_staff_id"><option value="0">My linked staff record</option><?php foreach ($staff as $person): ?><option value="<?= (int)$person['id'] ?>"><?= e($person['last_name'] . ' ' . $person['first_name']) ?><?= $person['position'] ? ' — ' . e($person['position']) : '' ?></option><?php endforeach; ?></select></div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Follow-up</label><input class="form-control" type="date" name="follow_up_date"></div>
                <div class="col-6"><label class="form-label">Risk level</label><select class="form-select" name="risk_level"><option value="none">None</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
              </div>
              <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['held','scheduled','follow_up','closed','referred'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><label class="form-label">Resources</label><input class="form-control" name="resources_shared" maxlength="255"></div>
              </div>
              <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="parent_involved" id="cs_parent"><label class="form-check-label" for="cs_parent">Parent involved</label></div>
              <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_confidential" id="cs_confidential" checked><label class="form-check-label" for="cs_confidential">Confidential</label></div>
              <button class="btn btn-primary">Save session</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="card app-card mb-3">
          <div class="card-header">Counselling sessions <span class="badge text-bg-light"><?= count($counselling) ?></span></div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student</th><th>Session</th><th>Category</th><th>Counsellor</th><th>Risk</th><th>Status</th></tr></thead><tbody>
<?php if (!$counselling): ?><tr><td colspan="6" class="text-center text-muted py-4">No counselling sessions recorded.</td></tr><?php endif; ?>
<?php foreach ($counselling as $session): ?>
            <tr><td><?= e($studentName($session)) ?><div class="small text-muted"><?= e($session['admission_number']) ?></div></td>
              <td class="small">#<?= (int)$session['session_number'] ?> · <?= e(date('d M Y', strtotime($session['session_date']))) ?><?php if ($session['concerns']): ?><div class="text-muted"><?= e(mb_substr($session['concerns'], 0, 60)) ?></div><?php endif; ?></td>
              <td><?= e(ucfirst($session['category'])) ?></td>
              <td class="small"><?= e($session['counsellor_name'] ?: '—') ?></td>
              <td><span class="badge text-bg-<?= $session['risk_level'] === 'high' ? 'danger' : ($session['risk_level'] === 'medium' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($session['risk_level'])) ?></span></td>
              <td><span class="badge text-bg-<?= $session['status'] === 'closed' ? 'success' : ($session['status'] === 'follow_up' ? 'warning' : 'info') ?>"><?= e(str_replace('_', ' ', $session['status'])) ?></span></td></tr>
<?php endforeach; ?>
            </tbody>
          </table></div>
        </div>

        <div class="card app-card">
          <div class="card-header">Welfare and support cases</div>
          <div class="card-body pb-0">
            <form method="post" action="<?= url('health/welfare/save') ?>" class="row g-2 align-items-end mb-3">
              <?= csrf_field() ?>
              <div class="col-md-4"><label class="form-label" for="w_student">Student</label><select class="form-select" id="w_student" name="student_id" required><option value="">Choose…</option><?php foreach ($students as $student): ?><option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' — ' . $student['last_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label" for="w_category">Category</label><select class="form-select" id="w_category" name="category"><?php foreach (['orphan','special_needs','bullying','lack_of_basic_needs','abuse','neglect','mental_health','substance','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label" for="w_date">Identified</label><input class="form-control" id="w_date" type="date" name="date_identified" value="<?= date('Y-m-d') ?>"></div>
              <div class="col-md-2"><button class="btn btn-outline-primary w-100">Add case</button></div>
              <div class="col-12"><label class="form-label" for="w_description">Concern / description</label><textarea class="form-control" id="w_description" name="description" rows="2" required></textarea></div>
              <div class="col-md-3"><label class="form-label" for="w_support">Support type</label><select class="form-select" id="w_support" name="support_type"><option value="">—</option><?php foreach (['counselling','uniform','feeding','scholarship','medical','family_visit','referral','other'] as $value): ?><option value="<?= $value ?>"><?= e(ucwords(str_replace('_', ' ', $value))) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label" for="w_responsible">Responsible staff</label><select class="form-select" id="w_responsible" name="responsible_staff"><option value="0">—</option><?php foreach ($staff as $person): ?><option value="<?= (int)$person['id'] ?>"><?= e($person['last_name'] . ' ' . $person['first_name']) ?></option><?php endforeach; ?></select></div>
              <div class="col-md-3"><label class="form-label" for="w_agency">External agency</label><input class="form-control" id="w_agency" name="external_agency" maxlength="160"></div>
              <div class="col-md-3"><label class="form-label" for="w_followup">Follow-up</label><input class="form-control" id="w_followup" type="date" name="follow_up_date"></div>
              <div class="col-12"><label class="form-label" for="w_support_given">Support provided</label><textarea class="form-control" id="w_support_given" name="support_provided" rows="2"></textarea></div>
            </form>
          </div>
          <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" data-table>
            <thead><tr><th>Student</th><th>Category</th><th>Concern</th><th>Support</th><th>Follow-up</th><th>Status</th></tr></thead><tbody>
<?php if (!$welfare): ?><tr><td colspan="6" class="text-center text-muted py-4">No welfare cases recorded.</td></tr><?php endif; ?>
<?php foreach ($welfare as $case): ?>
            <tr><td><?= e($studentName($case)) ?><div class="small text-muted"><?= e($case['admission_number']) ?></div></td>
              <td><?= e(ucwords(str_replace('_', ' ', $case['category']))) ?></td>
              <td class="small"><span class="d-inline-block text-truncate" style="max-width:16rem" title="<?= e($case['description']) ?>"><?= e($case['description']) ?></span></td>
              <td class="small"><?= e($case['support_type'] ? ucwords(str_replace('_', ' ', $case['support_type'])) : '—') ?></td>
              <td class="small"><?= $case['follow_up_date'] ? e(date('d M Y', strtotime($case['follow_up_date']))) : '—' ?></td>
              <td>
<?php if ($canEdit): ?>
                <form method="post" action="<?= url('health/welfare/status') ?>">
                  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$case['id'] ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
<?php foreach (['open','in_progress','supported','closed','referred'] as $value): ?>
                    <option value="<?= $value ?>" <?= $case['status'] === $value ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $value))) ?></option>
<?php endforeach; ?>
                  </select>
                </form>
<?php else: ?>
                <span class="badge text-bg-<?= $welfareBadge($case['status']) ?>"><?= e(str_replace('_', ' ', $case['status'])) ?></span>
<?php endif; ?>
              </td></tr>
<?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
      </div>
    </div>
<?php endif; ?>
  </div>
</div>
