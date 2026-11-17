<?php
$statusColor = fn(string $s): string => match($s) {
    'submitted','accepted' => 'success',
    'rejected','returned_for_correction' => 'danger',
    'validated' => 'info',
    default => 'secondary'
};
$grantStatus = fn(string $s): string => match($s) {
    'received','reconciled' => 'success',
    'partially_received' => 'warning',
    'returned' => 'danger',
    default => 'secondary'
};
?>
<div class="page-head">
  <div><h1 class="h4 mb-0">Government compliance</h1><p class="text-muted small mb-0">EMIS returns, enrolment data, and capitation grant tracking.</p></div>
</div>

<!-- ============================================================ EMIS returns -->
<div class="row g-3 mb-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">New EMIS return</div>
      <div class="card-body">
        <form method="post" action="<?= url('compliance/emis/create') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Return type <span class="text-danger">*</span></label>
            <select class="form-select" name="return_type" required>
              <option value="EMIS_termly">EMIS termly</option>
              <option value="EMIS_annual">EMIS annual</option>
              <option value="census">Census</option>
              <option value="inspection">Inspection</option>
              <option value="other">Other</option>
            </select>
            <span class="invalid-feedback d-block" data-error-for="return_type"></span>
          </div>
          <div class="mb-2">
            <label class="form-label">Period label <span class="text-danger">*</span></label>
            <input class="form-control" name="period_label" placeholder="e.g. Term 1 2025" maxlength="60" required>
            <span class="invalid-feedback d-block" data-error-for="period_label"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Academic year <span class="text-danger">*</span></label>
              <select class="form-select" name="academic_year_id" required>
                <option value="">— year —</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y['id'] ?>"><?= e($y['name']) ?><?= $y['is_active'] ? ' ✓' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="academic_year_id"></span>
            </div>
            <div class="col">
              <label class="form-label">Term</label>
              <select class="form-select" name="term_id">
                <option value="">— term —</option>
                <?php foreach ($terms as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-3">
            <div class="col">
              <label class="form-label">Total learners</label>
              <input class="form-control" name="total_learners" type="number" min="0" value="0">
              <span class="invalid-feedback d-block" data-error-for="total_learners"></span>
            </div>
            <div class="col">
              <label class="form-label">Teachers</label>
              <input class="form-control" name="total_teachers" type="number" min="0" value="0">
            </div>
            <div class="col">
              <label class="form-label">Support staff</label>
              <input class="form-control" name="total_support_staff" type="number" min="0" value="0">
            </div>
          </div>
          <button class="btn btn-primary" type="submit">Save draft</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">EMIS returns <span class="badge text-bg-light"><?= count($emisReturns) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Period</th><th>Type</th><th>Year</th><th>Learners</th><th>Teachers</th><th>Status</th><?php if ($canEdit || $canApprove): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$emisReturns): ?><tr><td colspan="7" class="text-center text-muted py-4">No EMIS returns yet.</td></tr><?php endif; ?>
            <?php foreach ($emisReturns as $r): ?>
            <tr>
              <td><strong><?= e($r['period_label']) ?></strong><?= $r['reference_number'] ? '<div class="small text-muted">' . e($r['reference_number']) . '</div>' : '' ?></td>
              <td class="small"><?= e(str_replace('_', ' ', $r['return_type'])) ?></td>
              <td class="small"><?= e($r['year_name']) ?></td>
              <td><?= (int)$r['total_learners'] ?></td>
              <td><?= (int)$r['total_teachers'] ?></td>
              <td><span class="badge text-bg-<?= $statusColor($r['status']) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
              <?php if ($canEdit || $canApprove): ?>
              <td class="text-nowrap">
                <?php if ($r['status'] === 'draft' && $canApprove): ?>
                  <form method="post" action="<?= url('compliance/emis/validate') ?>" data-ajax class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-info">Validate</button>
                  </form>
                <?php elseif ($r['status'] === 'validated' && $canApprove): ?>
                  <form method="post" action="<?= url('compliance/emis/submit') ?>" data-ajax class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-success">Submit to MoES</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================ Capitation grants -->
<div class="row g-3">
<?php if ($canAdd): ?>
  <div class="col-xl-4">
    <div class="card app-card">
      <div class="card-header">Record capitation grant</div>
      <div class="card-body">
        <form method="post" action="<?= url('compliance/grants/create') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="mb-2">
            <label class="form-label">Grant type <span class="text-danger">*</span></label>
            <select class="form-select" name="grant_type" required>
              <option value="">— select —</option>
              <option value="UPE">UPE</option>
              <option value="USE">USE</option>
              <option value="other_grant">Other grant</option>
            </select>
            <span class="invalid-feedback d-block" data-error-for="grant_type"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Academic year <span class="text-danger">*</span></label>
              <select class="form-select" name="academic_year_id" required>
                <option value="">— year —</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y['id'] ?>"><?= e($y['name']) ?><?= $y['is_active'] ? ' ✓' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <span class="invalid-feedback d-block" data-error-for="academic_year_id"></span>
            </div>
            <div class="col">
              <label class="form-label">Term</label>
              <select class="form-select" name="term_id">
                <option value="">— term —</option>
                <?php foreach ($terms as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Amount expected (UGX)</label>
            <input class="form-control" name="amount_expected" type="number" min="0" step="1000" value="0">
            <span class="invalid-feedback d-block" data-error-for="amount_expected"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Beneficiaries</label>
              <input class="form-control" name="beneficiary_count" type="number" min="0" value="">
            </div>
            <div class="col">
              <label class="form-label">Rate / learner</label>
              <input class="form-control" name="per_learner_rate" type="number" min="0" step="100" value="">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reference number</label>
            <input class="form-control" name="reference_number" maxlength="80">
          </div>
          <button class="btn btn-primary" type="submit">Save grant record</button>
        </form>
      </div>
    </div>
  </div>
<?php endif; ?>

  <div class="<?= $canAdd ? 'col-xl-8' : 'col-12' ?>">
    <div class="card app-card">
      <div class="card-header">Capitation grants <span class="badge text-bg-light"><?= count($grants) ?></span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Type</th><th>Year / term</th><th>Expected (UGX)</th><th>Received (UGX)</th><th>Beneficiaries</th><th>Status</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (!$grants): ?><tr><td colspan="7" class="text-center text-muted py-4">No grants recorded yet.</td></tr><?php endif; ?>
            <?php foreach ($grants as $g): ?>
            <tr>
              <td><strong><?= e($g['grant_type']) ?></strong></td>
              <td class="small"><?= e($g['year_name']) ?><?= $g['term_name'] ? ' / ' . e($g['term_name']) : '' ?></td>
              <td><?= number_format((float)$g['amount_expected']) ?></td>
              <td><?= number_format((float)$g['amount_received']) ?></td>
              <td><?= $g['beneficiary_count'] ? (int)$g['beneficiary_count'] : '—' ?></td>
              <td><span class="badge text-bg-<?= $grantStatus($g['status']) ?>"><?= e(str_replace('_', ' ', $g['status'])) ?></span></td>
              <?php if ($canEdit): ?>
              <td>
                <?php if (!in_array($g['status'], ['reconciled', 'returned'], true)): ?>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal"
                  data-bs-target="#receiptModal"
                  data-grant-id="<?= (int)$g['id'] ?>">Record receipt</button>
                <?php else: ?>
                <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Receipt modal -->
<?php if ($canEdit): ?>
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Record grant receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="post" action="<?= url('compliance/grants/receipt') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="receiptGrantId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Amount received (UGX) <span class="text-danger">*</span></label>
            <input class="form-control" name="amount_received" type="number" min="0" step="1000" required>
            <span class="invalid-feedback d-block" data-error-for="amount_received"></span>
          </div>
          <div class="mb-3">
            <label class="form-label">Date received <span class="text-danger">*</span></label>
            <input class="form-control" name="received_date" type="date" required>
            <span class="invalid-feedback d-block" data-error-for="received_date"></span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save receipt</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('receiptModal').addEventListener('show.bs.modal', function(e) {
  document.getElementById('receiptGrantId').value = e.relatedTarget.dataset.grantId;
});
</script>
<?php endif; ?>
