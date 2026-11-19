<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Recruitment</h1>
    <p class="text-muted small mb-0">Manage vacancies, applications, shortlisting and appointments</p>
  </div>
  <div class="text-end">
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vacancyModal"><i class="bi bi-plus"></i> New vacancy</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card app-card mb-3">
      <div class="card-header">Vacancies</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Title</th>
                <th>Department</th>
                <th>Position</th>
                <th>Type</th>
                <th>Slots</th>
                <th>Closing</th>
                <th>Status</th>
                <th>Apps</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php if (!$vacancies): ?><tr><td colspan="9" class="text-center text-muted py-4">No vacancies found.</td></tr><?php endif; ?>
<?php foreach ($vacancies as $vac): ?>
              <tr>
                <td><strong><?= e($vac['title']) ?></strong></td>
                <td><?= e($vac['department_name'] ?? '—') ?></td>
                <td><?= e($vac['position']) ?></td>
                <td><?= e(str_replace('_', ' ', $vac['employment_type'])) ?></td>
                <td><?= (int)$vac['slots'] ?></td>
                <td><?= e($vac['closing_date'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $vac['status'] === 'open' ? 'success' : ($vac['status'] === 'filled' ? 'secondary' : ($vac['status'] === 'closed' ? 'warning' : 'danger')) ?>"><?= e(ucfirst($vac['status'])) ?></span></td>
                <td><?= (int)$vac['application_count'] ?></td>
                <td class="text-end text-nowrap">
                  <?php if ($canAdd): ?>
                  <button class="btn btn-sm btn-outline-primary" onclick="editVacancy(<?= json_encode($vac) ?>)"><i class="bi bi-pencil"></i></button>
                  <form method="post" action="<?= url('hr/vacancies/status') ?>" class="d-inline" onsubmit="return confirm('Change status to <?= e($vac['status'] === 'open' ? 'closed' : 'open') ?>?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$vac['id'] ?>">
                    <input type="hidden" name="status" value="<?= $vac['status'] === 'open' ? 'closed' : 'open' ?>">
                    <button class="btn btn-sm btn-<?= $vac['status'] === 'open' ? 'outline-danger' : 'outline-success' ?>"><?= $vac['status'] === 'open' ? 'Close' : 'Open' ?></button>
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

  <div class="col-xl-4">
    <div class="card app-card mb-3">
      <div class="card-header">Recent applications</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr><th>Applicant</th><th>Vacancy</th><th>Status</th><th class="text-end">Action</th></tr>
            </thead>
            <tbody>
<?php if (!$applications): ?><tr><td colspan="4" class="text-center text-muted py-3">No applications found.</td></tr><?php endif; ?>
<?php foreach ($applications as $app): ?>
              <tr>
                <td><strong><?= e($app['full_name']) ?></strong><div class="small text-muted"><?= e($app['email'] ?? '—') ?></div></td>
                <td><?= e($app['vacancy_title'] ?? '—') ?></td>
                <td><span class="badge bg-<?= $app['status'] === 'appointed' ? 'success' : ($app['status'] === 'rejected' ? 'danger' : ($app['status'] === 'shortlisted' ? 'info' : 'secondary')) ?>"><?= e(ucfirst($app['status'])) ?></span></td>
                <td class="text-end text-nowrap">
                  <?php if ($canApprove): ?>
                  <button class="btn btn-sm btn-outline-primary" onclick="editApp(<?= json_encode($app) ?>)"><i class="bi bi-pencil"></i></button>
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

<?php if ($canAdd): ?>
<div class="modal fade" id="vacancyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('hr/vacancies/save') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">New vacancy</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="0">
          <div class="mb-3">
            <label class="form-label">Vacancy title</label>
            <input class="form-control" name="title" required maxlength="140">
          </div>
          <div class="mb-3">
            <label class="form-label">Department</label>
            <select class="form-select" name="department_id">
              <option value="">-- None --</option>
<?php foreach ($departments as $dept): ?>
              <option value="<?= (int)$dept['id'] ?>"><?= e($dept['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Position</label>
            <input class="form-control" name="position" required maxlength="120">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Employment type</label>
              <select class="form-select" name="employment_type">
                <option value="teaching">Teaching</option>
                <option value="non_teaching">Non-teaching</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Slots</label>
              <input class="form-control" type="number" name="slots" value="1" min="1" max="100">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Minimum qualification</label>
            <input class="form-control" name="min_qualification" maxlength="140">
          </div>
          <div class="mb-3">
            <label class="form-label">Experience required</label>
            <input class="form-control" name="experience_required" maxlength="80">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Salary min (UGX)</label>
              <input class="form-control" type="number" step="0.01" name="salary_min" min="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Salary max (UGX)</label>
              <input class="form-control" type="number" step="0.01" name="salary_max" min="0">
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Published date</label>
              <input class="form-control" type="date" name="published_date">
            </div>
            <div class="col-md-6">
              <label class="form-label">Closing date</label>
              <input class="form-control" type="date" name="closing_date">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="draft">Draft</option>
              <option value="open">Open</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save vacancy</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="appModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('hr/applications/decide') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Application decision</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="0">
          <div class="mb-3">
            <label class="form-label">Applicant</label>
            <input class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Vacancy</label>
            <input class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Action</label>
            <select class="form-select" name="action" required>
              <option value="shortlist">Shortlist</option>
              <option value="interview">Invite to interview</option>
              <option value="appoint">Appoint</option>
              <option value="reject">Reject</option>
              <option value="withdraw">Withdraw</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Interview date</label>
            <input class="form-control" type="datetime-local" name="interview_date">
          </div>
          <div class="mb-3">
            <label class="form-label">Interview notes</label>
            <textarea class="form-control" name="interview_notes" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Decision notes</label>
            <textarea class="form-control" name="decision_notes" rows="2"></textarea>
          </div>
          <div class="mb-3" id="appointStaffField" style="display:none;">
            <label class="form-label">Appoint as staff member</label>
            <select class="form-select" name="appoint_staff_id">
<?php foreach ($staff as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['staff_number'] . ')') ?></option>
<?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save decision</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($canAdd): ?>
<div class="modal fade" id="appSubmitModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('hr/applications/save') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">New application</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="0">
          <div class="mb-3">
            <label class="form-label">Vacancy</label>
            <select class="form-select" name="vacancy_id" required>
<?php foreach ($vacancies as $vac): ?>
              <option value="<?= (int)$vac['id'] ?>"><?= e($vac['title']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Full name</label>
            <input class="form-control" name="full_name" required maxlength="140">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" maxlength="120">
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" maxlength="40">
          </div>
          <div class="mb-3">
            <label class="form-label">Qualification</label>
            <input class="form-control" name="qualification" maxlength="160">
          </div>
          <div class="mb-3">
            <label class="form-label">Experience (years)</label>
            <input class="form-control" type="number" name="experience_years" min="0" max="100">
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <option value="received">Received</option>
              <option value="shortlisted">Shortlisted</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save application</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  function editVacancy(v) {
    const m = new bootstrap.Modal(document.getElementById('vacancyModal'));
    const form = m._element.querySelector('form');
    form.id.value = v.id;
    form.title.value = v.title;
    form.department_id.value = v.department_id || '';
    form.position.value = v.position;
    form.employment_type.value = v.employment_type;
    form.slots.value = v.slots;
    form.min_qualification.value = v.min_qualification || '';
    form.experience_required.value = v.experience_required || '';
    form.salary_min.value = v.salary_min || '';
    form.salary_max.value = v.salary_max || '';
    form.published_date.value = v.published_date || '';
    form.closing_date.value = v.closing_date || '';
    form.status.value = v.status;
    m.show();
  }

  function editApp(a) {
    const m = new bootstrap.Modal(document.getElementById('appModal'));
    const form = m._element.querySelector('form');
    form.id.value = a.id;
    form.querySelector('input[readonly]').value = a.full_name;
    form.elements['action'].value = a.status;
    form.interview_date.value = a.interview_date ? a.interview_date.replace(' ', 'T') : '';
    form.interview_notes.value = a.interview_notes || '';
    form.decision_notes.value = a.decision_notes || '';
    const appointField = document.getElementById('appointStaffField');
    appointField.style.display = a.status === 'appointed' ? 'block' : 'none';
    form.elements['action'].addEventListener('change', function () {
      appointField.style.display = this.value === 'appoint' ? 'block' : 'none';
    });
    m.show();
  }
</script>
