<?php
$canAdd = $canAdd ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">UNEB National Results</h1>
      <p class="text-muted small mb-0">Store national examination results released by UNEB.</p>
    </div>
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="resultModal" data-empty><i class="bi bi-plus"></i> Record Result</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>National Results</span>
        <form method="get" action="<?= url('uneb/results') ?>" class="d-flex gap-2">
          <select class="form-select form-select-sm" name="level_id">
            <option value="0">All levels</option>
            <?php foreach ($levels as $level): ?>
            <option value="<?= (int)$level['id'] ?>" <?= $selectedLevel === (int)$level['id'] ? 'selected' : '' ?>><?= e($level['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="form-control form-control-sm" type="number" name="year" min="2000" max="2100" value="<?= $selectedYear ?>" placeholder="Exam year">
          <button class="btn btn-outline-primary btn-sm" type="submit">Filter</button>
        </form>
      </div>
      <div class="card-body p-0">
<?php if (!$results): ?>
        <div class="text-muted text-center p-4">No UNEB results recorded yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Exam Year</th><th>Index Number</th><th>Full Name</th><th>Gender</th><th>Level</th><th>Result Code</th><th>Overall Level</th><th style="width:14%">CA Submitted</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($results as $r): ?>
              <tr>
                <td><?= e($r['exam_year']) ?></td>
                <td><?= e($r['index_number']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e(ucfirst($r['gender'])) ?></td>
                <td><?= e($r['level_name']) ?></td>
                <td><?= e($r['result_code'] ?? '—') ?></td>
                <td><?= e($r['overall_level'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= (int)($r['ca_score_submitted'] ?? 0) ? 'success' : 'secondary' ?>"><?= (int)($r['ca_score_submitted'] ?? 0) ? 'Yes' : 'No' ?></span></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record UNEB Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('uneb/save-result') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="result_level">UNEB Level <span class="text-danger">*</span></label>
              <select class="form-select" id="result_level" name="level_id" required>
                <option value="">Choose level</option>
                <?php foreach ($levels as $level): ?>
                <option value="<?= (int)$level['id'] ?>"><?= e($level['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="result_exam_year">Exam Year <span class="text-danger">*</span></label>
              <input class="form-control" id="result_exam_year" name="exam_year" type="number" min="2000" max="2100" value="<?= date('Y') ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="result_index">Index Number <span class="text-danger">*</span></label>
              <input class="form-control" id="result_index" name="index_number" maxlength="30" required placeholder="e.g. 2026/U/12345">
            </div>
            <div class="col-12">
              <label class="form-label" for="result_name">Full Name <span class="text-danger">*</span></label>
              <input class="form-control" id="result_name" name="full_name" maxlength="160" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="result_gender">Gender <span class="text-danger">*</span></label>
              <select class="form-select" id="result_gender" name="gender" required>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="result_code">Result Code</label>
              <select class="form-select" id="result_code" name="result_code">
                <option value="">None</option>
                <option value="Result 1">Result 1 (Certificate)</option>
                <option value="Result 2">Result 2 (Transcript only)</option>
                <option value="Result 3">Result 3 (Transcript only)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="result_overall">Overall Level / Grade</label>
              <input class="form-control" id="result_overall" name="overall_level" maxlength="20" placeholder="e.g. Division 2 / Grade B">
            </div>
            <div class="col-md-4">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="ca_score_submitted" id="result_ca_submitted" value="1">
                <label class="form-check-label" for="result_ca_submitted">CA scores submitted to UNEB</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label" for="result_subjects">Subject Results (summary)</label>
              <textarea class="form-control" id="result_subjects" name="subject_results" rows="3" maxlength="500" placeholder="e.g. Math: B, English: C, Physics: A"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="result_notes">Notes</label>
              <input class="form-control" id="result_notes" name="notes" maxlength="255">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Record Result</button>
        </div>
      </form>
    </div>
  </div>
</div>
