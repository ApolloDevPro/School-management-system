<?php
$canAdd = $canAdd ?? false;
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Register UNEB Candidate</h1>
    <p class="text-muted small mb-0">Register a candidate for UCE or UACE examination.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('uneb') ?>">Back to candidates</a>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Candidate Details</div>
      <div class="card-body">
        <form method="post" action="<?= url('uneb/save-candidate') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-person-vcard"></i> Candidate details</div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="index_number">UNEB Index Number <span class="text-danger">*</span></label>
              <input class="form-control" id="index_number" name="index_number" maxlength="30" required placeholder="e.g. 2026/U/12345">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
              <input class="form-control" id="full_name" name="full_name" maxlength="160" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="gender" name="gender" required>
                <option value="">Choose</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="date_of_birth">Date of Birth</label>
              <input class="form-control" id="date_of_birth" name="date_of_birth" type="date">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="student_id">Linked Student (optional)</label>
              <select class="form-select" id="student_id" name="student_id">
                <option value="">No student linked</option>
                <?php foreach ($students as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['admission_number'] . ' - ' . $s['last_name'] . ' ' . $s['first_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="level_id">UNEB Level <span class="text-danger">*</span></label>
              <select class="form-select" id="level_id" name="level_id" required>
                <option value="">Choose level</option>
                <?php foreach ($levels as $level): ?>
                <option value="<?= (int)$level['id'] ?>"><?= e($level['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="status">Status</label>
              <select class="form-select" id="status" name="status">
                <option value="registered">Registered</option>
                <option value="entered">Entered</option>
                <option value="withdrawn">Withdrawn</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <input class="form-control" id="notes" name="notes" maxlength="255">
            </div>
          </div>
          <div class="form-section"><i class="bi bi-journal-text"></i> Subjects entered</div>
          <div class="row g-3 mt-4">
            <div class="col-12">
              <label class="form-label">Subjects Entered</label>
              <div class="row g-2">
<?php foreach ($subjects as $subj): ?>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?= (int)$subj['id'] ?>" id="subj_<?= (int)$subj['id'] ?>">
                  <label class="form-check-label" for="subj_<?= (int)$subj['id'] ?>"><?= e($subj['name']) ?> <span class="badge text-bg-light ms-1"><?= e($subj['code']) ?></span></label>
                </div>
              </div>
<?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="d-grid gap-2 mt-4">
            <button class="btn btn-primary" type="submit">Register Candidate</button>
            <a class="btn btn-outline-secondary" href="<?= url('uneb') ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
