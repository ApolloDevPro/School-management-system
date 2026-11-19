<?php
$canAdd = $canAdd ?? false;
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">Budgets</h1>
      <p class="text-muted small mb-0">Termly or annual budgets by department, compared against actual spending.</p>
    </div>
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="budgetModal" data-empty><i class="bi bi-plus"></i> New Budget</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-body p-0">
<?php if (!$budgets): ?>
        <div class="text-muted text-center p-4">No budgets defined yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Budget Name</th><th>Type</th><th>Year / Term</th><th>Department</th><th>Total Amount</th><th>Status</th><th style="width:16%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($budgets as $b): ?>
              <tr>
                <td><?= e($b['name']) ?></td>
                <td><?= e(ucfirst($b['budget_type'])) ?></td>
                <td><?= e($b['name'] ?? '') ?> / <?= e($b['term_name'] ?? '—') ?></td>
                <td><?= e($b['department_name'] ?? '—') ?></td>
                <td>UGX <?= number_format((float)$b['total_amount'], 2) ?></td>
                <td><span class="badge text-bg-<?= $b['status'] === 'approved' ? 'success' : ($b['status'] === 'draft' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($b['status'])) ?></span></td>
                <td class="text-nowrap">
<?php if ($canEdit): ?>
                  <button class="btn btn-outline-primary btn-sm" type="button" data-toggle-modal="budgetModal" data-edit='{"id":<?= (int)$b['id'] ?>,"name":"<?= e($b['name']) ?>","budget_type":"<?= e($b['budget_type']) ?>","academic_year_id":<?= (int)$b['academic_year_id'] ?>,"term_id":<?= (int)$b['term_id'] ?? 0 ?>,"department_id":<?= (int)$b['department_id'] ?? 0 ?>,"total_amount":<?= (float)$b['total_amount'] ?>,"status":"<?= e($b['status']) ?>","notes":"<?= e($b['notes'] ?? '') ?>}'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-outline-secondary btn-sm" type="button" data-toggle-modal="budgetLinesModal" data-budget-id="<?= (int)$b['id'] ?>"><i class="bi bi-list"></i> Lines</button>
<?php endif; ?>
<?php if ($canDelete): ?>
                  <form method="post" action="<?= url('finance/budgets/delete') ?>" class="d-inline" data-confirm="Delete this budget?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i></button>
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
</div>

<div class="modal fade" id="budgetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="budgetModalTitle">New Budget</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('finance/budgets/save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="budget_id" value="">
        <div class="modal-body">
          <div class="form-section"><i class="bi bi-file-earmark-text"></i> Budget details</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="budget_name">Budget Name <span class="text-danger">*</span></label>
              <input class="form-control" id="budget_name" name="name" maxlength="140" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="budget_type">Budget Type</label>
              <select class="form-select" id="budget_type" name="budget_type">
                <option value="termly">Termly</option>
                <option value="annual">Annual</option>
                <option value="project">Project</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="budget_year">Academic Year <span class="text-danger">*</span></label>
              <select class="form-select" id="budget_year" name="academic_year_id" required>
                <option value="">Choose year</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y['id'] ?>" <?= !empty($y['is_active']) ? 'selected' : '' ?>><?= e($y['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="budget_term">Term</label>
              <select class="form-select" id="budget_term" name="term_id">
                <option value="">No term</option>
                <?php foreach ($terms as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="budget_dept">Department</label>
              <select class="form-select" id="budget_dept" name="department_id">
                <option value="">No department</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-section"><i class="bi bi-cash-stack"></i> Amount and status</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="budget_amount">Total Amount (UGX)</label>
              <input class="form-control" id="budget_amount" name="total_amount" type="number" min="0" step="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="budget_status">Status</label>
              <select class="form-select" id="budget_status" name="status">
                <option value="draft">Draft</option>
                <option value="submitted">Submitted</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="budget_notes">Notes</label>
              <input class="form-control" id="budget_notes" name="notes" maxlength="255">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Budget</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="budgetLinesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Budget Lines</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Account</label>
          <select class="form-select" id="bl_account"></select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col">
            <label class="form-label">Planned Amount (UGX)</label>
            <input class="form-control" id="bl_planned" type="number" min="0" step="0.01">
          </div>
          <div class="col-auto d-flex align-items-end">
            <button class="btn btn-primary" type="button" id="bl_add">Add Line</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" id="bl_table">
            <thead><tr><th>Account</th><th>Type</th><th>Planned</th><th>Actual</th><th>Variance</th><th></th></tr></thead>
            <tbody id="bl_rows"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
var currentBudgetId = null;

document.addEventListener('DOMContentLoaded', function () {
  // Load accounts for budget lines
  fetch('<?= url('finance/chart-of-accounts/list') ?>')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var sel = document.getElementById('bl_account');
      if (!sel || !data) return;
      (data || []).forEach(function (a) {
        var o = document.createElement('option');
        o.value = a.id;
        o.textContent = a.account_code + ' - ' + a.account_name + ' (' + a.account_type + ')';
        sel.appendChild(o);
      });
    });

  var budgetModal = document.getElementById('budgetModal');
  budgetModal.addEventListener('show.bs.modal', function (event) {
    var trigger = event.relatedTarget;
    if (!trigger) return;
    var edit = trigger.getAttribute('data-edit');
    if (!edit) {
      document.getElementById('budgetModalTitle').textContent = 'New Budget';
      document.getElementById('budget_id').value = '';
      budgetModal.querySelector('form').reset();
      return;
    }
    var data = JSON.parse(edit);
    document.getElementById('budgetModalTitle').textContent = 'Edit Budget';
    document.getElementById('budget_id').value = data.id || '';
    ['name','budget_type','academic_year_id','term_id','department_id','total_amount','status','notes'].forEach(function (k) {
      var f = budgetModal.querySelector('[name="' + k + '"]');
      if (f) f.value = data[k] ?? '';
    });
  });

  var linesModal = document.getElementById('budgetLinesModal');
  linesModal.addEventListener('show.bs.modal', function (event) {
    currentBudgetId = event.relatedTarget.getAttribute('data-budget-id');
    loadLines();
  });

  document.getElementById('bl_add').addEventListener('click', function () {
    if (!currentBudgetId) return;
    var accountId = document.getElementById('bl_account').value;
    var planned = document.getElementById('bl_planned').value;
    if (!accountId || !planned) return;
    fetch('<?= url('finance/budgets/lines/save') ?>', {
      method: 'POST',
      headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
      body: new FormData((function () { var f = document.createElement('form'); f.action = '<?= url('finance/budgets/lines/save') ?>'; var i1 = document.createElement('input'); i1.name = 'budget_id'; i1.value = currentBudgetId; f.appendChild(i1); var i2 = document.createElement('input'); i2.name = 'account_id'; i2.value = accountId; f.appendChild(i2); var i3 = document.createElement('input'); i3.name = 'planned_amount'; i3.value = planned; f.appendChild(i3); return f; })())
    }).then(function (r) { return r.json(); }).then(function (resp) {
      if (resp.ok) { loadLines(); document.getElementById('bl_planned').value = ''; }
    });
  });
});

function loadLines() {
  fetch('<?= url('finance/budgets/lines') ?>?budget_id=' + currentBudgetId)
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var tbody = document.getElementById('bl_rows');
      tbody.innerHTML = '';
      (data || []).forEach(function (l) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + l.account_name + '</td><td>' + l.account_type + '</td><td>' + l.planned_amount + '</td><td>' + l.actual_amount + '</td><td>' + l.variance + '</td><td><button class="btn btn-sm btn-outline-danger" onclick="deleteLine(' + l.id + ')"><i class="bi bi-trash"></i></button></td>';
        tbody.appendChild(tr);
      });
    });
}

function deleteLine(id) {
  if (!confirm('Delete this budget line?')) return;
  var f = document.createElement('form');
  f.method = 'POST';
  f.action = '<?= url('finance/budgets/lines/delete') ?>';
  f.innerHTML = '<input type="hidden" name="_token" value="' + document.querySelector('meta[name="csrf-token"]').content + '"><input type="hidden" name="id" value="' + id + '">';
  document.body.appendChild(f);
  f.submit();
}
</script>
