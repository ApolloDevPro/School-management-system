<?php /* Full student profile: bio, guardians, subjects, fees, attendance, results and discipline. */ ?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h1>
    <p class="text-muted small mb-0">
      <?= e($student['admission_number']) ?>
<?php if ($student['registration_number']): ?> &middot; Reg: <?= e($student['registration_number']) ?><?php endif; ?>
      &middot; <?= e($student['class_name'] ?? 'No class') ?><?= $student['stream_name'] ? ' · ' . e($student['stream_name']) : '' ?>
      &middot; <span class="badge text-bg-light"><?= e(str_replace('_', ' ', ucfirst($student['status']))) ?></span>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= url('students') ?>">Back to list</a>
    <a class="btn btn-outline-primary" href="<?= url('students/subjects', ['student_id' => (int)$student['id']]) ?>">Subjects</a>
<?php if ($canEdit): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="studentEditModal">Edit</button>
<?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card app-card mb-3">
      <div class="card-header">Bio-data</div>
      <div class="card-body">
        <div class="text-center mb-3">
          <?php if (!empty($student['photo_path'])): ?>
            <img src="<?= e(asset($student['photo_path'])) ?>" alt="Student photo" class="rounded-circle border" style="width:120px;height:120px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-circle border bg-light d-inline-flex align-items-center justify-content-center text-muted" style="width:120px;height:120px;">
              <i class="bi bi-person" style="font-size:2.5rem;"></i>
            </div>
          <?php endif; ?>
        </div>
        <dl class="row mb-0 small">
          <dt class="col-5 text-muted">Gender</dt><dd class="col-7"><?= e(ucfirst($student['gender'])) ?></dd>
          <dt class="col-5 text-muted">Date of birth</dt><dd class="col-7"><?= $student['date_of_birth'] ? e(date('d M Y', strtotime($student['date_of_birth']))) : '—' ?></dd>
          <dt class="col-5 text-muted">Place of birth</dt><dd class="col-7"><?= e($student['place_of_birth'] ?: '—') ?></dd>
          <dt class="col-5 text-muted">Nationality</dt><dd class="col-7"><?= e($student['nationality']) ?></dd>
          <dt class="col-5 text-muted">Religion</dt><dd class="col-7"><?= e($student['religion'] ?: '—') ?></dd>
          <dt class="col-5 text-muted">NIN</dt><dd class="col-7"><?= e($student['nin'] ?: '—') ?></dd>
          <dt class="col-5 text-muted">UNEB LIN</dt><dd class="col-7"><?= e($student['uneb_lin'] ?: '—') ?></dd>
          <dt class="col-5 text-muted">Type</dt><dd class="col-7"><?= e(ucfirst($student['student_type'])) ?></dd>
          <dt class="col-5 text-muted">House</dt><dd class="col-7"><?= e($student['house_name'] ?: '—') ?></dd>
          <dt class="col-5 text-muted">Admission date</dt><dd class="col-7"><?= $student['admission_date'] ? e(date('d M Y', strtotime($student['admission_date']))) : '—' ?></dd>
          <dt class="col-5 text-muted">Previous school</dt><dd class="col-7"><?= e($student['previous_school'] ?: '—') ?></dd>
          <dt class="col-5 text-muted">Next of kin</dt><dd class="col-7"><?= e($student['next_of_kin'] ?: '—') ?></dd>
<?php if ((int)$student['special_needs']): ?>
          <dt class="col-5 text-muted">Special needs</dt><dd class="col-7"><?= e($student['special_needs_notes'] ?: 'Yes') ?></dd>
<?php endif; ?>
        </dl>
      </div>
    </div>

<?php if ($canEdit): ?>
    <div class="card app-card mb-3">
      <div class="card-header">Class &amp; stream allocation</div>
      <div class="card-body">
        <form method="post" action="<?= url('students/allocate') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
          <div class="mb-2">
            <label class="form-label small" for="a_class">Class</label>
            <select class="form-select form-select-sm" id="a_class" name="class_id" required>
              <option value="0">— Choose class —</option>
<?php foreach ($classes as $class): ?>
              <option value="<?= (int)$class['id'] ?>" <?= (int)$student['current_class_id'] === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?> (<?= e($class['level']) ?>)</option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small" for="a_stream">Stream</label>
            <select class="form-select form-select-sm" id="a_stream" name="stream_id" data-stream-select>
              <option value="0">— None —</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small" for="a_house">House</label>
            <select class="form-select form-select-sm" id="a_house" name="house_id">
              <option value="0">— None —</option>
<?php foreach ($houses as $house): ?>
              <option value="<?= (int)$house['id'] ?>" <?= (int)$student['house_id'] === (int)$house['id'] ? 'selected' : '' ?>><?= e($house['name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small" for="a_type">Student type</label>
            <select class="form-select form-select-sm" id="a_type" name="student_type">
              <option value="day" <?= $student['student_type'] === 'day' ? 'selected' : '' ?>>Day</option>
              <option value="boarding" <?= $student['student_type'] === 'boarding' ? 'selected' : '' ?>>Boarding</option>
            </select>
          </div>
          <button class="btn btn-sm btn-primary" type="submit">Save allocation</button>
        </form>
      </div>
    </div>
<?php endif; ?>
  </div>

  <div class="col-lg-8">
    <ul class="nav nav-pills mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-guardians" type="button">Guardians (<?= count($guardians) ?>)</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-subjects" type="button">Subjects (<?= count($subjects) ?>)</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-history" type="button">Class history</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-discipline" type="button">Discipline (<?= count($incidents) ?>)</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-documents" type="button">Documents (<?= count($documents) ?>)</button></li>
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade show active" id="tab-guardians">
        <div class="card app-card">
          <div class="card-body">
<?php if (!$guardians): ?>
            <p class="text-muted small mb-3">No guardians linked yet.</p>
<?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-3">
                <thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Fee payer</th><th class="text-end"></th></tr></thead>
                <tbody>
<?php foreach ($guardians as $guardian): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($guardian['first_name'] . ' ' . $guardian['last_name']) ?><?= (int)$guardian['link_is_primary'] ? ' <span class="badge text-bg-primary">Primary</span>' : '' ?></td>
                    <td><?= e(ucfirst($guardian['link_relationship'])) ?></td>
                    <td><?= e($guardian['phone'] ?: '—') ?></td>
                    <td><?= (int)$guardian['is_fee_payer'] ? 'Yes' : 'No' ?></td>
                    <td class="text-end">
<?php if ($canEdit): ?>
                      <form method="post" action="<?= url('guardians/unlink') ?>" class="d-inline" data-confirm="Remove this guardian from the student?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                        <input type="hidden" name="guardian_id" value="<?= (int)$guardian['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                      </form>
<?php endif; ?>
                    </td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
<?php endif; ?>
<?php if ($canEdit): ?>
            <div class="border-top pt-3">
              <h2 class="h6">Link a guardian</h2>
              <form method="post" action="<?= url('guardians/link') ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <input type="hidden" name="student_id" value="<?= (int)$student['id'] ?>">
                <div class="col-md-5">
                  <label class="form-label small" for="g_sel">Guardian</label>
                  <select class="form-select form-select-sm" id="g_sel" name="guardian_id" required>
                    <option value="">Choose…</option>
<?php foreach (Guardian::listForSchool(school_id()) as $guardian): ?>
                    <option value="<?= (int)$guardian['id'] ?>"><?= e($guardian['first_name'] . ' ' . $guardian['last_name'] . ' · ' . ($guardian['phone'] ?: 'no phone')) ?></option>
<?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small" for="g_rel">Relationship</label>
                  <select class="form-select form-select-sm" id="g_rel" name="relationship">
<?php foreach (['father' => 'Father', 'mother' => 'Mother', 'guardian' => 'Guardian', 'sponsor' => 'Sponsor', 'relative' => 'Relative', 'other' => 'Other'] as $value => $label): ?>
                    <option value="<?= $value ?>"><?= $label ?></option>
<?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2 d-flex gap-3">
                  <div class="form-check">
                    <input class="form-check-input" id="g_primary" name="is_primary" type="checkbox">
                    <label class="form-check-label small" for="g_primary">Primary</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" id="g_fee" name="is_fee_payer" type="checkbox">
                    <label class="form-check-label small" for="g_fee">Fee payer</label>
                  </div>
                </div>
                <div class="col-md-2">
                  <button class="btn btn-sm btn-primary w-100" type="submit">Link</button>
                </div>
              </form>
            </div>
<?php endif; ?>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-subjects">
        <div class="card app-card">
          <div class="card-body">
<?php if ($year): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="text-muted small">Registered for <?= e($year['name']) ?></span>
              <a class="btn btn-sm btn-outline-primary" href="<?= url('students/subjects', ['student_id' => (int)$student['id']]) ?>">Manage subjects</a>
            </div>
<?php endif; ?>
<?php if (!$subjects): ?>
            <p class="text-muted small mb-0">No subjects registered.</p>
<?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Code</th><th>Subject</th><th>Category</th><th>Status</th></tr></thead>
                <tbody>
<?php foreach ($subjects as $subject): ?>
                  <tr>
                    <td><?= e($subject['code']) ?></td>
                    <td><?= e($subject['name']) ?></td>
                    <td><span class="badge text-bg-light"><?= e($subject['category']) ?></span></td>
                    <td><?= e($subject['status']) ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
<?php endif; ?>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-history">
        <div class="card app-card">
          <div class="card-body">
<?php if (!$history): ?>
            <p class="text-muted small mb-0">No placement history yet.</p>
<?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Class</th><th>Stream</th><th>Year</th><th>Action</th><th>Notes</th></tr></thead>
                <tbody>
<?php foreach ($history as $entry): ?>
                  <tr>
                    <td class="text-nowrap"><?= e(date('d M Y', strtotime($entry['effective_date']))) ?></td>
                    <td><?= e($entry['class_name']) ?></td>
                    <td><?= e($entry['stream_name'] ?? '—') ?></td>
                    <td><?= e($entry['year_name']) ?></td>
                    <td><span class="badge text-bg-light"><?= e($entry['action']) ?></span><?= (int)$entry['is_current'] ? ' <span class="badge text-bg-success">Current</span>' : '' ?></td>
                    <td class="small text-muted"><?= e($entry['notes'] ?? '') ?></td>
                  </tr>
<?php endforeach; ?>
                </tbody>
              </table>
            </div>
<?php endif; ?>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-discipline">
        <div class="card app-card">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted small">Recorded incidents</span>
              <a class="btn btn-sm btn-outline-primary" href="<?= url('students/discipline') ?>">Open discipline module</a>
            </div>
<?php if (!$incidents): ?>
            <p class="text-muted small mb-0">No discipline incidents recorded.</p>
<?php else: ?>
            <ul class="list-unstyled mb-0">
<?php foreach ($incidents as $incident): ?>
              <li class="py-2 border-bottom">
                <div class="d-flex justify-content-between">
                  <strong><?= e(str_replace('_', ' ', ucfirst($incident['category']))) ?></strong>
                  <span class="small text-muted"><?= e(date('d M Y', strtotime($incident['incident_date']))) ?></span>
                </div>
                <div class="small"><?= e($incident['description']) ?></div>
                <div class="small text-muted">Sanction: <?= e($incident['sanction']) ?> &middot; Status: <?= e($incident['status']) ?></div>
              </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </div>
        </div>
      </div>

      <div class="tab-pane fade" id="tab-documents">
        <div class="card app-card">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted small">Uploaded documents</span>
              <a class="btn btn-sm btn-outline-primary" href="<?= url('students/documents', ['student_id' => (int)$student['id']]) ?>">Manage documents</a>
            </div>
<?php if (!$documents): ?>
            <p class="text-muted small mb-0">No documents uploaded.</p>
<?php else: ?>
            <ul class="list-unstyled mb-0">
<?php foreach ($documents as $document): ?>
              <li class="d-flex justify-content-between py-2 border-bottom">
                <span>
                  <a href="<?= asset($document['file_path']) ?>" target="_blank"><?= e($document['title']) ?></a>
                  <span class="badge text-bg-light"><?= e(str_replace('_', ' ', $document['doc_type'])) ?></span>
<?php if ((int)$document['is_verified']): ?>
                  <span class="badge text-bg-success">Verified</span>
<?php endif; ?>
                </span>
                <span class="small text-muted"><?= e(date('d M Y', strtotime($document['created_at']))) ?></span>
              </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($canEdit): ?>
<div class="modal fade" id="studentEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="<?= url('students/update') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit student — <?= e($student['admission_number']) ?></h5>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="p_photo">Profile photo</label>
              <input class="form-control" id="p_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
              <div class="form-text">JPG, PNG or WEBP, max 2 MB. Uploading a new photo replaces the current one.</div>
              <span class="invalid-feedback d-block" data-error-for="photo"></span>
              <?php if (!empty($student['photo_path'])): ?>
                <div class="mt-2"><img src="<?= e(asset($student['photo_path'])) ?>" alt="Current photo" class="rounded border" style="width:72px;height:72px;object-fit:cover;"></div>
              <?php endif; ?>
            </div>
            <div class="form-section"><i class="bi bi-person-vcard"></i> Personal details</div>
            <div class="col-md-3">
              <label class="form-label" for="e_admission">Admission no.</label>
              <input class="form-control" id="e_admission" name="admission_number" maxlength="40" value="<?= e($student['admission_number']) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_regno">Registration no.</label>
              <input class="form-control" id="e_regno" name="registration_number" maxlength="40" value="<?= e($student['registration_number'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_lin">UNEB LIN</label>
              <input class="form-control" id="e_lin" name="uneb_lin" maxlength="40" value="<?= e($student['uneb_lin'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_nin">NIN</label>
              <input class="form-control" id="e_nin" name="nin" maxlength="40" value="<?= e($student['nin'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_first">First name</label>
              <input class="form-control" id="e_first" name="first_name" maxlength="60" value="<?= e($student['first_name']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_middle">Middle name</label>
              <input class="form-control" id="e_middle" name="middle_name" maxlength="60" value="<?= e($student['middle_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_last">Last name</label>
              <input class="form-control" id="e_last" name="last_name" maxlength="60" value="<?= e($student['last_name']) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_gender">Gender</label>
              <select class="form-select" id="e_gender" name="gender">
                <option value="male" <?= $student['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= $student['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_dob">Date of birth</label>
              <input class="form-control" id="e_dob" name="date_of_birth" type="date" value="<?= e($student['date_of_birth'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_type">Type</label>
              <select class="form-select" id="e_type" name="student_type">
                <option value="day" <?= $student['student_type'] === 'day' ? 'selected' : '' ?>>Day</option>
                <option value="boarding" <?= $student['student_type'] === 'boarding' ? 'selected' : '' ?>>Boarding</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="e_status">Status</label>
              <select class="form-select" id="e_status" name="status">
<?php foreach (['active' => 'Active', 'applicant' => 'Applicant', 'completed' => 'Completed', 'graduated' => 'Graduated', 'suspended' => 'Suspended'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= $student['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="form-section"><i class="bi bi-mortarboard"></i> Background</div>
            <div class="col-md-4">
              <label class="form-label" for="e_religion">Religion</label>
              <input class="form-control" id="e_religion" name="religion" maxlength="60" value="<?= e($student['religion'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_kin">Next of kin</label>
              <input class="form-control" id="e_kin" name="next_of_kin" maxlength="140" value="<?= e($student['next_of_kin'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_prev_school">Previous school</label>
              <input class="form-control" id="e_prev_school" name="previous_school" maxlength="160" value="<?= e($student['previous_school'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_prev_result">Previous result</label>
              <input class="form-control" id="e_prev_result" name="previous_result" maxlength="120" value="<?= e($student['previous_result'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_pob">Place of birth</label>
              <input class="form-control" id="e_pob" name="place_of_birth" maxlength="120" value="<?= e($student['place_of_birth'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="e_nation">Nationality</label>
              <input class="form-control" id="e_nation" name="nationality" maxlength="60" value="<?= e($student['nationality']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="e_adm_date">Admission date</label>
              <input class="form-control" id="e_adm_date" name="admission_date" type="date" value="<?= e($student['admission_date'] ?? '') ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" id="e_special" name="special_needs" type="checkbox" <?= (int)$student['special_needs'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="e_special">Special needs</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label" for="e_special_notes">Special needs notes</label>
              <input class="form-control" id="e_special_notes" name="special_needs_notes" maxlength="255" value="<?= e($student['special_needs_notes'] ?? '') ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
window.SSMS_STREAMS = <?= json_encode(
    array_reduce($classes, function (array $carry, array $class): array {
        $carry[(int)$class['id']] = array_values(array_map(fn ($s) => ['id' => (int)$s['id'], 'name' => $s['name']], Stream::forClass((int)$class['id'])));
        return $carry;
    }, []),
    JSON_UNESCAPED_SLASHES
) ?>;
</script>
