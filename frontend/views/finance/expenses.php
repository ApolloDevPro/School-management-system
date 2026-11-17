<?php
$canAdd = $canAdd ?? false;
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">Expenses</h1>
      <p class="text-muted small mb-0">Record school expenditures by account, department and payment method.</p>
    </div>
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="expenseModal" data-empty><i class="bi bi-plus"></i> Record Expense</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-body p-0">
<?php if (!$expenses): ?>
        <div class="text-muted text-center p-4">No expenses recorded yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Voucher</th><th>Date</th><th>Account</th><th>Payee</th><th>Category</th><th>Amount</th><th>Status</th><th style="width:14%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($expenses as $e): ?>
              <tr>
                <td><?= e($e['voucher_number']) ?></td>
                <td><?= e($e['expense_date']) ?></td>
                <td><?= e($e['account_name'] ?? '—') ?></td>
                <td><?= e($e['payee']) ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $e['category']))) ?></td>
                <td>UGX <?= number_format((float)$e['amount'], 2) ?></td>
                <td><span class="badge text-bg-<?= $e['approval_status'] === 'approved' ? 'success' : ($e['approval_status'] === 'pending' ? 'warning' : 'danger') ?>"><?= e(ucfirst($e['approval_status'])) ?></span></td>
                <td class="text-nowrap">
<?php if ($canEdit): ?>
                  <button class="btn btn-outline-primary btn-sm" type="button" data-toggle-modal="expenseModal" data-edit='{"id":<?= (int)$e['id'] ?>,"voucher_number":"<?= e($e['voucher_number']) ?>","account_id":<?= (int)$e['account_id'] ?>,"department_id":<?= (int)$e['department_id'] ?? 0 ?>,"academic_year_id":<?= (int)$e['academic_year_id'] ?? 0 ?>,"term_id":<?= (int)$e['term_id'] ?? 0 ?>,"amount":<?= (float)$e['amount'] ?>,"expense_date":"<?= e($e['expense_date']) ?>","payee":"<?= e($e['payee']) ?>","description":"<?= e($e['description']) ?>","category":"<?= e($e['category']) ?>","payment_method":"<?= e($e['payment_method']) ?>","approval_status":"<?= e($e['approval_status']) ?>}'><i class="bi bi-pencil"></i></button>
<?php endif; ?>
<?php if ($canDelete): ?>
                  <form method="post" action="<?= url('finance/expenses/delete') ?>" class="d-inline" data-confirm="Delete this expense?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
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

<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="expenseModalTitle">Record Expense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('finance/expenses/save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="expense_id" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="exp_voucher">Voucher Number</label>
              <input class="form-control" id="exp_voucher" name="voucher_number" maxlength="40">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_account">Account <span class="text-danger">*</span></label>
              <select class="form-select" id="exp_account" name="account_id" required>
                <option value="">Choose expense account</option>
                <?php foreach ($accounts as $acct): ?>
                <option value="<?= (int)$acct['id'] ?>"><?= e($acct['account_code'] . ' - ' . $acct['account_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_dept">Department</label>
              <select class="form-select" id="exp_dept" name="department_id">
                <option value="">No department</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= (int)$dept['id'] ?>"><?= e($dept['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_year">Academic Year</label>
              <select class="form-select" id="exp_year" name="academic_year_id">
                <option value="">No year</option>
                <?php foreach ($years as $yr): ?>
                <option value="<?= (int)$yr['id'] ?>"><?= e($yr['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_date">Expense Date <span class="text-danger">*</span></label>
              <input class="form-control" id="exp_date" name="expense_date" type="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_payee">Payee <span class="text-danger">*</span></label>
              <input class="form-control" id="exp_payee" name="payee" maxlength="160" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_description">Description <span class="text-danger">*</span></label>
              <input class="form-control" id="exp_description" name="description" maxlength="255" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_amount">Amount (UGX) <span class="text-danger">*</span></label>
              <input class="form-control" id="exp_amount" name="amount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_category">Category</label>
              <select class="form-select" id="exp_category" name="category">
                <option value="other">Other</option>
                <option value="utilities">Utilities</option>
                <option value="salaries">Salaries</option>
                <option value="repairs">Repairs</option>
                <option value="supplies">Supplies</option>
                <option value="transport">Transport</option>
                <option value="learning_materials">Learning Materials</option>
                <option value="food">Food</option>
                <option value="maintenance">Maintenance</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_method">Payment Method</label>
              <select class="form-select" id="exp_method" name="payment_method">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
                <option value="cheque">Cheque</option>
                <option value="mobile_money">Mobile Money</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="exp_status">Approval Status</label>
              <select class="form-select" id="exp_status" name="approval_status">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="paid">Paid</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Expense</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('expenseModal').addEventListener('show.bs.modal', function (event) {
  var trigger = event.relatedTarget;
  if (!trigger) return;
  var edit = trigger.getAttribute('data-edit');
  if (!edit) {
    document.getElementById('expenseModalTitle').textContent = 'Record Expense';
    document.getElementById('expense_id').value = '';
    this.querySelector('form').reset();
    document.getElementById('exp_date').value = <?= json_encode(date('Y-m-d')) ?>;
    return;
  }
  var data = JSON.parse(edit);
  document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
  document.getElementById('expense_id').value = data.id || '';
  ['voucher_number','account_id','department_id','academic_year_id','term_id','amount','expense_date','payee','description','category','payment_method','approval_status'].forEach(function (k) {
    var f = this.querySelector('[name="' + k + '"]');
    if (f) f.value = data[k] ?? '';
  }, this);
});
</script>
