<div class="page-head">
  <div>
    <h1 class="h4 mb-0">New CA Submission</h1>
    <p class="text-muted small mb-0">Create a continuous assessment submission for UNEB.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('uneb/ca-submission') ?>">Back to submissions</a>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card app-card">
      <div class="card-header">Submission Details</div>
      <div class="card-body">
        <form method="post" action="<?= url('uneb/save-ca-submission') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="ca_level">UNEB Level <span class="text-danger">*</span></label>
              <select class="form-select" id="ca_level" name="level_id" required>
                <option value="">Choose level</option>
                <?php foreach ($levels as $level): ?>
                <option value="<?= (int)$level['id'] ?>"><?= e($level['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ca_year">Academic Year <span class="text-danger">*</span></label>
              <select class="form-select" id="ca_year" name="academic_year_id" required>
                <option value="">Choose year</option>
                <?php foreach ($years as $year): ?>
                <option value="<?= (int)$year['id'] ?>" <?= !empty($year['is_active']) ? 'selected' : '' ?>><?= e($year['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ca_term">Term</label>
              <select class="form-select" id="ca_term" name="term_id">
                <option value="">No term</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ca_status">Status</label>
              <select class="form-select" id="ca_status" name="status">
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="ca_type">Submission Type</label>
              <select class="form-select" id="ca_type" name="submission_type">
                <option value="internal">Internal</option>
                <option value="uneb_portal">UNEB Portal</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="ca_notes">Notes</label>
              <input class="form-control" id="ca_notes" name="notes" maxlength="255">
            </div>
          </div>
          <div class="d-grid gap-2 mt-4">
            <button class="btn btn-primary" type="submit">Create Submission</button>
            <a class="btn btn-outline-secondary" href="<?= url('uneb/ca-submission') ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
