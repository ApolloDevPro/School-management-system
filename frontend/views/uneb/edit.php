<?php
$canEdit = $canEdit ?? false;
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Edit Candidate</h1>
    <p class="text-muted small mb-0">Edit UNEB candidate <?= e($candidate['index_number'] ?? '') ?>.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('uneb') ?>">Back to candidates</a>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card app-card">
      <div class="card-header">Candidate Details</div>
      <div class="card-body">
        <form method="post" action="<?= url('uneb/update-candidate') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$candidate['id'] ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="index_number">UNEB Index Number <span class="text-danger">*</span></label>
              <input class="form-control" id="index_number" name="index_number" maxlength="30" required value="<?= e($candidate['index_number'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="full_name">Full Name <span class="text-danger">*</span></label>
              <input class="form-control" id="full_name" name="full_name" maxlength="160" required value="<?= e($candidate['full_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="gender">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="gender" name="gender" required>
                <option value="male" <?= (($candidate['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= (($candidate['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="date_of_birth">Date of Birth</label>
              <input class="form-control" id="date_of_birth" name="date_of_birth" type="date" value="<?= e($candidate['date_of_birth'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="student_id">Linked Student (optional)</label>
              <select class="form-select" id="student_id" name="student_id">
                <option value="">No student linked</option>
<?php
// students not passed to edit view; fetch inline is not possible in view, so skip student list
?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="level_id">UNEB Level <span class="text-danger">*</span></label>
              <select class="form-select" id="level_id" name="level_id" required>
<?php foreach ($levels as $level): ?>
                <option value="<?= (int)$level['id'] ?>" <?= (int)$level['id'] === (int)($candidate['level_id'] ?? 0) ? 'selected' : '' ?>><?= e($level['name']) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="status">Status</label>
              <select class="form-select" id="status" name="status">
                <option value="registered" <?= (($candidate['status'] ?? '') === 'registered') ? 'selected' : '' ?>>Registered</option>
                <option value="entered" <?= (($candidate['status'] ?? '') === 'entered') ? 'selected' : '' ?>>Entered</option>
                <option value="withdrawn" <?= (($candidate['status'] ?? '') === 'withdrawn') ? 'selected' : '' ?>>Withdrawn</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="notes">Notes</label>
              <input class="form-control" id="notes" name="notes" maxlength="255" value="<?= e($candidate['notes'] ?? '') ?>">
            </div>
          </div>
          <div class="row g-3 mt-4">
            <div class="col-12">
              <label class="form-label">Subjects Entered</label>
              <div class="row g-2">
<?php foreach ($subjects as $subj): $checked = in_array((int)$subj['id'], $selectedSubjects ?? [], true) ? 'checked' : ''; ?>
              <div class="col-md-4">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?= (int)$subj['id'] ?>" id="subj_<?= (int)$subj['id'] ?>" <?= $checked ?>>
                  <label class="form-check-label" for="subj_<?= (int)$subj['id'] ?>"><?= e($subj['name']) ?> <span class="badge text-bg-light ms-1"><?= e($subj['code']) ?></span></label>
                </div>
              </div>
<?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="d-grid gap-2 mt-4">
            <button class="btn btn-primary" type="submit">Update Candidate</button>
            <a class="btn btn-outline-secondary" href="<?= url('uneb') ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
