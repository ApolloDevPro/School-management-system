<?php
$canEdit = $canEdit ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">CA Candidate Scores</h1>
      <p class="text-muted small mb-0">Enter continuous assessment and project work scores for submission <?= e($submission['index_number'] ?? $submission['level_name'] ?? '') ?>.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= url('uneb/ca-submission') ?>">Back to submissions</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Level</div>
      <div class="stat-value"><?= e($submission['level_name'] ?? '—') ?></div>
      <div class="stat-note">UNEB level</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Status</div>
      <div class="stat-value"><span class="badge text-bg-<?= $submission['status'] === 'submitted' ? 'success' : 'warning' ?>"><?= e(ucfirst($submission['status'])) ?></span></div>
      <div class="stat-note">Submission status</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Scores Entered</div>
      <div class="stat-value"><?= count($scores) ?></div>
      <div class="stat-note">Candidates scored</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary">
      <div class="stat-label">Year / Term</div>
      <div class="stat-value"><?= e(date('Y', strtotime($submission['created_at'] ?? ''))) ?> / <?= (int)($submission['term_id'] ?? 0) ?></div>
      <div class="stat-note">Academic period</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header">Candidate Scores</div>
      <div class="card-body">
<?php if (!$scores): ?>
        <div class="text-muted text-center p-4">No candidate scores entered yet. Add candidates to the submission first, then enter their scores here.</div>
<?php else: ?>
        <form method="post" action="<?= url('uneb/save-ca-scores') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="submission_id" value="<?= (int)$submission['id'] ?>">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Index Number</th><th>Candidate</th><th>Subject</th><th>CA Score</th><th>Project Score</th><th>Overall Score</th>
                </tr>
              </thead>
              <tbody>
<?php foreach ($scores as $sc): ?>
              <tr>
                <td><?= e($sc['index_number']) ?></td>
                <td><?= e($sc['full_name']) ?></td>
                <td><?= e($sc['subject_name']) ?> <span class="badge text-bg-light ms-1"><?= e($sc['subject_code']) ?></span></td>
                <td>
                  <input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="ca_score[<?= (int)$sc['candidate_id'] ?>][<?= (int)$sc['subject_id'] ?>][ca]" value="<?= e($sc['ca_score'] ?? '') ?>">
                </td>
                <td>
                  <input class="form-control form-control-sm" type="number" step="0.01" min="0" max="3" name="ca_score[<?= (int)$sc['candidate_id'] ?>][<?= (int)$sc['subject_id'] ?>][project]" value="<?= e($sc['project_score'] ?? '') ?>">
                </td>
                <td>
                  <input class="form-control form-control-sm" type="number" step="0.01" min="0" max="100" name="ca_score[<?= (int)$sc['candidate_id'] ?>][<?= (int)$sc['subject_id'] ?>][overall]" value="<?= e($sc['overall_score'] ?? '') ?>">
                </td>
              </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save Scores</button>
            <?php if ($canEdit): ?>
            <button class="btn btn-outline-success" type="button" data-toggle-modal="submitModal"><i class="bi bi-send"></i> Submit to UNEB</button>
            <?php endif; ?>
          </div>
        </form>

        <div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Submit CA to UNEB</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form method="post" action="<?= url('uneb/ca-submit') ?>" data-ajax>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$submission['id'] ?>">
                <div class="modal-body">
                  <p class="text-muted small">This will mark the submission as <strong>submitted</strong> to the UNEB learners' portal. Scores can no longer be edited after submission.</p>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-success">Submit to UNEB</button>
                </div>
              </form>
            </div>
          </div>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>
