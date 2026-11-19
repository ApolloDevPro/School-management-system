<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Staff Appraisals</h1>
    <p class="text-muted small mb-0">Record periodic performance assessments of staff</p>
  </div>
  <div class="text-end">
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#appraisalModal"><i class="bi bi-plus"></i> New appraisal</button>
    <?php endif; ?>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Staff</th>
            <th>Period</th>
            <th>Overall score</th>
            <th>Status</th>
            <th>Appraised by</th>
            <th>Appraisal date</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
<?php if (!$appraisals): ?><tr><td colspan="7" class="text-center text-muted py-4">No appraisals found.</td></tr><?php endif; ?>
<?php foreach ($appraisals as $app): ?>
          <tr>
            <td><strong><?= e($app['last_name'] . ' ' . $app['first_name']) ?></strong><div class="small text-muted"><?= e($app['staff_number']) ?></div></td>
            <td><?= e($app['period_label']) ?><div class="small text-muted"><?= e($app['period_start'] ?? '—') ?> to <?= e($app['period_end'] ?? '—') ?></div></td>
            <td><?= $app['overall_score'] !== null ? number_format((float)$app['overall_score'], 1) : '—' ?></td>
            <td><span class="badge bg-<?= $app['status'] === 'completed' ? 'success' : ($app['status'] === 'acknowledged' ? 'info' : ($app['status'] === 'disputed' ? 'danger' : 'warning')) ?>"><?= e(ucfirst($app['status'])) ?></span></td>
            <td><?= e($app['appraiser_name'] ?? '—') ?></td>
            <td><?= e($app['appraisal_date'] ?? '—') ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('hr/appraisals') ?>#app-<?= (int)$app['id'] ?>"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($canAdd): ?>
<div class="modal fade" id="appraisalModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" action="<?= url('hr/appraisals/save') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">New staff appraisal</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="0">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Staff member</label>
              <select class="form-select" name="staff_id" required>
                <option value="">-- Select staff --</option>
<?php foreach ($staff as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['staff_number'] . ')') ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Period label</label>
              <input class="form-control" name="period_label" required maxlength="60" placeholder="e.g. 2026 Annual Review">
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="draft">Draft</option>
                <option value="completed">Completed</option>
              </select>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Period start</label>
              <input class="form-control" type="date" name="period_start">
            </div>
            <div class="col-md-4">
              <label class="form-label">Period end</label>
              <input class="form-control" type="date" name="period_end">
            </div>
            <div class="col-md-4">
              <label class="form-label">Overall score (0-100)</label>
              <input class="form-control" type="number" step="0.1" name="overall_score" min="0" max="100">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Appraiser</label>
            <select class="form-select" name="appraised_by" required>
<?php foreach ($staff as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name'] . ' (' . $s['staff_number'] . ')') ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Appraisal date</label>
              <input class="form-control" type="date" name="appraisal_date">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Assessment criteria (key = value)</label>
            <div id="criteriaRows" class="row g-2">
              <div class="col-md-6"><input class="form-control" name="criteria[]" placeholder="e.g. Punctuality"></div>
              <div class="col-md-6"><input class="form-control" name="criteria[]" placeholder="e.g. Excellent"></div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="addCriteriaRow()"><i class="bi bi-plus"></i> Add criterion</button>
          </div>
          <div class="mb-3">
            <label class="form-label">Comments</label>
            <textarea class="form-control" name="comments" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Targets for next period</label>
            <textarea class="form-control" name="targets" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Recommendations</label>
            <input class="form-control" name="recommendations" maxlength="255">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save appraisal</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  function addCriteriaRow() {
    const container = document.getElementById('criteriaRows');
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = `
      <div class="col-md-6"><input class="form-control" name="criteria[]" placeholder="Criterion"></div>
      <div class="col-md-5"><input class="form-control" name="criteria[]" placeholder="Rating / comment"></div>
      <div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.row.g-2.mb-2).remove()"><i class="bi bi-trash"></i></button></div>
    `;
    container.appendChild(row);
  }
</script>
