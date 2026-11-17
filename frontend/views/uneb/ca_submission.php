<?php
$canAdd = $canAdd ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">UNEB Continuous Assessment Submission</h1>
      <p class="text-muted small mb-0">Prepare and submit continuous assessment scores to the UNEB learners' portal.</p>
    </div>
    <?php if ($canAdd): ?>
    <a class="btn btn-primary" href="<?= url('uneb/ca-submission/new') ?>"><i class="bi bi-plus"></i> New Submission</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>CA Submissions</span>
        <form method="get" action="<?= url('uneb/ca-submission') ?>" class="d-flex gap-2">
          <select class="form-select form-select-sm" name="level_id">
            <option value="0">All levels</option>
            <?php foreach ($levels as $level): ?>
            <option value="<?= (int)$level['id'] ?>" <?= $selectedLevel === (int)$level['id'] ? 'selected' : '' ?>><?= e($level['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-outline-primary btn-sm" type="submit">Filter</button>
        </form>
      </div>
      <div class="card-body p-0">
<?php if (!$submissions): ?>
        <div class="text-muted text-center p-4">No CA submissions yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Level</th><th>Year / Term</th><th>Type</th><th>Status</th><th>Scores</th><th>Submitted</th><th style="width:18%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($submissions as $s): ?>
              <tr>
                <td><?= e($s['level_name']) ?></td>
                <td><?= e(date('Y', strtotime($s['created_at'] ?? ''))) ?> / Term <?= (int)($s['term_id'] ?? 0) ?></td>
                <td><?= e(ucfirst($s['submission_type'])) ?></td>
                <td><span class="badge text-bg-<?= $s['status'] === 'submitted' ? 'success' : ($s['status'] === 'draft' ? 'warning' : 'info') ?>"><?= e(ucfirst($s['status'])) ?></span></td>
                <td><?= (int)($s['subject_count'] ?? 0) ?> subject scores</td>
                <td><?= e($s['submitted_at'] ? date('d M Y', strtotime($s['submitted_at'])) : '—') ?></td>
                <td class="text-nowrap">
                  <a class="btn btn-outline-primary btn-sm" href="<?= url('uneb/ca-scores', ['id' => (int)$s['id']]) ?>"><i class="bi bi-pencil"></i> Scores</a>
<?php if ($s['status'] !== 'submitted'): ?>
                  <form method="post" action="<?= url('uneb/ca-submit') ?>" class="d-inline" data-confirm="Mark this submission as sent to UNEB?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <button class="btn btn-outline-success btn-sm" type="submit"><i class="bi bi-send"></i> Submit</button>
                  </form>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>
