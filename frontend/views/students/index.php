<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Students</h1>
    <p class="text-muted small mb-0">Registered students from admission to graduation</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= url('students/allocation') ?>">Bulk allocation</a>
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="studentModal" data-empty>Add student</button>
    <?php endif; ?>
    <?php if ($canAdd || $canEdit): ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= url('students/export-csv') ?>"><i class="bi bi-file-earmark-excel"></i> Export CSV</a>
    <?php endif; ?>
    <?php if ($canAdd): ?>
    <button class="btn btn-outline-secondary btn-sm" type="button" data-toggle-modal="importModal" data-empty><i class="bi bi-file-earmark-import"></i> Import CSV</button>
    <?php endif; ?>
  </div>
</div>

<div class="card app-card filter-bar mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('students') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="f_search">Search</label>
        <input class="form-control form-control-sm" id="f_search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name or admission no.">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="f_class">Class</label>
        <select class="form-select form-select-sm" id="f_class" name="class_id">
          <option value="0">All classes</option>
<?php foreach ($classes as $class): ?>
          <option value="<?= (int)$class['id'] ?>" <?= $filters['class_id'] === (int)$class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="f_status">Status</label>
        <select class="form-select form-select-sm" id="f_status" name="status">
          <option value="">Active &amp; related</option>
<?php foreach (['active' => 'Active', 'applicant' => 'Applicant', 'suspended' => 'Suspended', 'completed' => 'Completed', 'graduated' => 'Graduated', 'transferred_out' => 'Transferred out', 'withdrawn' => 'Withdrawn'] as $value => $label): ?>
          <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-sm btn-primary flex-fill" type="submit">Apply</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('students') ?>">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card app-card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-table>
        <thead>
          <tr>
            <th>Admission no.</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Class</th>
            <th>Stream</th>
            <th>Type</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
<?php if (!$students): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No students found.</td></tr>
<?php endif; ?>
<?php foreach ($students as $student): ?>
          <tr>
            <td class="fw-semibold"><?= e($student['admission_number']) ?></td>
            <td>
              <a href="<?= url('students/profile', ['id' => (int)$student['id']]) ?>" class="fw-semibold text-decoration-none">
                <?= e($student['last_name'] . ', ' . $student['first_name']) ?>
              </a>
            </td>
            <td><?= e(ucfirst($student['gender'])) ?></td>
            <td><?= e($student['class_name'] ?? '—') ?></td>
            <td><?= e($student['stream_name'] ?? '—') ?></td>
            <td><span class="badge text-bg-light"><?= e($student['student_type']) ?></span></td>
            <td>
<?php
              $badge = match ($student['status']) {
                  'active' => 'text-bg-success',
                  'suspended' => 'text-bg-warning',
                  'withdrawn', 'transferred_out' => 'text-bg-secondary',
                  'completed', 'graduated' => 'text-bg-info',
                  default => 'text-bg-light',
              };
?>
              <span class="badge <?= $badge ?>"><?= e(str_replace('_', ' ', ucfirst($student['status']))) ?></span>
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= url('students/profile', ['id' => (int)$student['id']]) ?>">Profile</a>
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('students/id-card', ['student_id' => (int)$student['id']]) ?>" target="_blank"><i class="bi bi-card-id"></i> ID</a>
<?php if ($canEdit): ?>
              <button class="btn btn-sm btn-outline-secondary" type="button"
                      data-toggle-modal="studentModal"
                      data-payload='<?= e(json_encode([
                          'id' => (int)$student['id'],
                          'photo_path' => $student['photo_path'] ?? '',
                          'admission_number' => $student['admission_number'],
                          'registration_number' => $student['registration_number'],
                          'uneb_lin' => $student['uneb_lin'],
                          'nin' => $student['nin'],
                          'first_name' => $student['first_name'],
                          'middle_name' => $student['middle_name'],
                          'last_name' => $student['last_name'],
                          'gender' => $student['gender'],
                          'date_of_birth' => $student['date_of_birth'],
                          'place_of_birth' => $student['place_of_birth'],
                          'nationality' => $student['nationality'],
                          'religion' => $student['religion'],
                          'next_of_kin' => $student['next_of_kin'],
                          'previous_school' => $student['previous_school'],
                          'previous_result' => $student['previous_result'],
                          'special_needs' => (int)$student['special_needs'],
                          'special_needs_notes' => $student['special_needs_notes'],
                          'student_type' => $student['student_type'],
                          'house_id' => $student['house_id'],
                          'current_class_id' => $student['current_class_id'],
                          'current_stream_id' => $student['current_stream_id'],
                          'admission_date' => $student['admission_date'],
                          'status' => $student['status'],
                      ])) ?>'>Edit</button>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="post" action="<?= url('students/store') ?>" data-update-action="<?= url('students/update') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="">
        <div class="modal-header">
          <h5 class="modal-title" data-student-title data-label-add="Add student" data-label-edit="Edit student">Add student</h5>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12"><div class="form-section"><i class="bi bi-id-card"></i> Identification</div></div>
            <div class="col-md-3">
              <label class="form-label" for="s_photo"><i class="bi bi-image"></i> Profile photo</label>
              <input class="form-control" id="s_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
              <div class="form-text">JPG, PNG or WEBP, max 2 MB.</div>
              <span class="invalid-feedback d-block" data-error-for="photo"></span>
              <div class="mt-2"><img data-photo-preview data-photo-placeholder="<?= e(asset('images/avatar-placeholder.svg')) ?>" src="" alt="" class="rounded border bg-light d-none" style="width:72px;height:72px;object-fit:cover;"></div>
            </div>
            <div class="col-md-3"><label class="form-label" for="s_admission"><i class="bi bi-card-text"></i> Admission no.</label><input class="form-control has-icon-left" id="s_admission" name="admission_number" maxlength="40" placeholder="Auto-generated if blank"><span class="input-icon bi bi-hashtag"></span></div>
            <div class="col-md-2"><label class="form-label" for="s_regno"><i class="bi bi-barcode"></i> Registration no.</label><input class="form-control has-icon-left" id="s_regno" name="registration_number" maxlength="40"><span class="input-icon bi bi-barcode"></span></div>
            <div class="col-md-2"><label class="form-label" for="s_lin"><i class="bi bi-award"></i> UNEB LIN</label><input class="form-control has-icon-left" id="s_lin" name="uneb_lin" maxlength="40"><span class="input-icon bi bi-award"></span></div>
            <div class="col-md-2"><label class="form-label" for="s_nin"><i class="bi bi-person-badge"></i> NIN</label><input class="form-control has-icon-left" id="s_nin" name="nin" maxlength="40"><span class="input-icon bi bi-person-badge"></span></div>

            <div class="col-12"><div class="form-section"><i class="bi bi-person"></i> Personal details</div></div>
            <div class="col-md-4"><label class="form-label" for="s_first"><i class="bi bi-person-fill"></i> First name <span class="text-danger">*</span></label><input class="form-control" id="s_first" name="first_name" maxlength="60" required></div>
            <div class="col-md-4"><label class="form-label" for="s_middle"><i class="bi bi-person"></i> Middle name</label><input class="form-control" id="s_middle" name="middle_name" maxlength="60"></div>
            <div class="col-md-4"><label class="form-label" for="s_last"><i class="bi bi-person-fill"></i> Last name <span class="text-danger">*</span></label><input class="form-control" id="s_last" name="last_name" maxlength="60" required></div>
            <div class="col-md-3"><label class="form-label" for="s_gender"><i class="bi bi-gender-ambiguous"></i> Gender <span class="text-danger">*</span></label><select class="form-select" id="s_gender" name="gender" required><option value="male">Male</option><option value="female">Female</option></select></div>
            <div class="col-md-3"><label class="form-label" for="s_dob"><i class="bi bi-calendar-event"></i> Date of birth</label><input class="form-control" id="s_dob" name="date_of_birth" type="date"></div>
            <div class="col-md-3"><label class="form-label" for="s_pob"><i class="bi bi-geo-alt"></i> Place of birth</label><input class="form-control" id="s_pob" name="place_of_birth" maxlength="120"></div>
            <div class="col-md-3"><label class="form-label" for="s_nation"><i class="bi bi-globe"></i> Nationality</label><input class="form-control" id="s_nation" name="nationality" maxlength="60" value="Ugandan"></div>
            <div class="col-md-4"><label class="form-label" for="s_religion"><i class="bi bi-cross2"></i> Religion</label><input class="form-control" id="s_religion" name="religion" maxlength="60"></div>
            <div class="col-md-4"><label class="form-label" for="s_kin"><i class="bi bi-people"></i> Next of kin</label><input class="form-control" id="s_kin" name="next_of_kin" maxlength="140"></div>
            <div class="col-md-4"><label class="form-label" for="s_prev_school"><i class="bi bi-building"></i> Previous school</label><input class="form-control" id="s_prev_school" name="previous_school" maxlength="160"></div>
            <div class="col-md-6"><label class="form-label" for="s_prev_result"><i class="bi bi-file-earmark-text"></i> Previous result</label><input class="form-control" id="s_prev_result" name="previous_result" maxlength="120" placeholder="e.g. PLE 12 aggregate"></div>

            <div class="col-12"><div class="form-section"><i class="bi bi-school"></i> Enrollment</div></div>
            <div class="col-md-3"><label class="form-label" for="s_type"><i class="bi bi-house-door"></i> Type</label><select class="form-select" id="s_type" name="student_type"><option value="day">Day</option><option value="boarding">Boarding</option></select></div>
            <div class="col-md-3"><label class="form-label" for="s_class"><i class="bi bi-layout-three-columns"></i> Class</label><select class="form-select" id="s_class" name="current_class_id"><option value="0">- Not allocated -</option>
<?php foreach ($classes as $class): ?>
                <option value="<?= (int)$class['id'] ?>"><?= e($class['name']) ?></option>
<?php endforeach; ?>
              </select></div>
            <div class="col-md-3"><label class="form-label" for="s_stream"><i class="bi bi-layout-sidebar"></i> Stream</label><select class="form-select" id="s_stream" name="current_stream_id" data-stream-select><option value="0">- None -</option></select></div>
            <div class="col-md-3"><label class="form-label" for="s_house"><i class="bi bi-houses"></i> House</label><select class="form-select" id="s_house" name="house_id"><option value="0">- None -</option>
<?php foreach ($houses as $house): ?>
                <option value="<?= (int)$house['id'] ?>"><?= e($house['name']) ?></option>
<?php endforeach; ?>
              </select></div>
            <div class="col-md-3"><label class="form-label" for="s_admission_date"><i class="bi bi-calendar-range"></i> Admission date</label><input class="form-control" id="s_admission_date" name="admission_date" type="date" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-3"><label class="form-label" for="s_status"><i class="bi bi-activity"></i> Status</label><select class="form-select" id="s_status" name="status">
<?php foreach (['active' => 'Active', 'applicant' => 'Applicant', 'suspended' => 'Suspended'] as $value => $label): ?>
                <option value="<?= $value ?>"><?= $label ?></option>
<?php endforeach; ?>
              </select></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" id="s_special" name="special_needs" type="checkbox"><label class="form-check-label" for="s_special"><i class="bi bi-heart-pulse"></i> Student has special needs</label></div></div>
            <div class="col-12"><label class="form-label" for="s_special_notes"><i class="bi bi-chat-text"></i> Special needs notes</label><textarea class="form-control" id="s_special_notes" name="special_needs_notes" rows="2" maxlength="255" placeholder="Describe support requirements, if any"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit" data-student-submit data-label-add="Add student" data-label-edit="Save changes"><i class="bi bi-save"></i> <span data-student-submit-text>Add student</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.SSMS_STREAMS = <?= json_encode(
    array_reduce($classes, function (array $carry, array $class): array {
        $carry[(int)$class['id']] = array_values(array_map(fn ($s) => ['id' => (int)$s['id'], 'name' => $s['name']], Stream::forClass((int)$class['id'])));
        return $carry;
    }, []),
    JSON_UNESCAPED_SLASHES
) ?>;
</script>

<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-import me-2"></i> Import students from CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('students/import-csv') ?>" enctype="multipart/form-data" data-ajax>
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label"><i class="bi bi-file-earmark-text me-2"></i> CSV file <span class="text-danger">*</span></label>
            <div class="field-input">
              <input class="form-control has-icon-left" type="file" name="csv_file" accept=".csv" required>
              <span class="input-icon bi bi-upload"></span>
            </div>
            <div class="form-text">Columns: Admission No, First Name, Last Name, Gender, Date of Birth, Class, Stream, House, Status, Admission Date</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-import"></i> Import</button>
        </div>
      </form>
    </div>
  </div>
</div>
