<?php
$canAdd = $canAdd ?? false;
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">Petty Cash</h1>
      <p class="text-muted small mb-0">Manage petty cash funds and vouchers for small expenditures.</p>
    </div>
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="fundModal" data-empty><i class="bi bi-wallet2"></i> New Fund</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-body p-0">
<?php if (!$funds): ?>
        <div class="text-muted text-center p-4">No petty cash funds defined yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Fund Name</th><th>Custodian</th><th>Opening Balance</th><th>Current Balance</th><th>Status</th><th style="width:14%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($funds as $f): ?>
              <tr>
                <td><?= e($f['fund_name']) ?></td>
                <td><?= e($f['custodian_name'] ?? '—') ?></td>
                <td>UGX <?= number_format((float)$f['opening_balance'], 2) ?></td>
                <td>UGX <strong><?= number_format((float)$f['current_balance'], 2) ?></strong></td>
                <td><span class="badge text-bg-<?= $f['status'] === 'open' ? 'success' : ($f['status'] === 'suspended' ? 'warning' : 'danger') ?>"><?= e(ucfirst($f['status'])) ?></span></td>
                <td class="text-nowrap">
<?php if ($canEdit): ?>
                  <button class="btn btn-outline-primary btn-sm" type="button" data-toggle-modal="fundModal" data-edit='{"id":<?= (int)$f['id'] ?>,"fund_name":"<?= e($f['fund_name']) ?>","opening_balance":<?= (float)$f['opening_balance'] ?>,"custodian_staff_id":<?= (int)$f['custodian_staff_id'] ?? 0 ?>,"account_id":<?= (int)$f['account_id'] ?? 0 ?>,"opened_on":"<?= e($f['opened_on']) ?>","status":"<?= e($f['status']) ?>}'><i class="bi bi-pencil"></i></button>
<?php endif; ?>
<?php if ($canDelete): ?>
                  <form method="post" action="<?= url('finance/petty-cash/delete-fund') ?>" class="d-inline" data-confirm="Delete this petty cash fund?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
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

<div class="modal fade" id="fundModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="fundModalTitle">Petty Cash Fund</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('finance/petty-cash/save-fund') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="fund_id" value="">
        <div class="modal-body">
          <div class="form-section"><i class="bi bi-wallet2"></i> Fund details</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="fund_name">Fund Name <span class="text-danger">*</span></label>
              <input class="form-control" id="fund_name" name="fund_name" maxlength="80" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="fund_opening">Opening Balance (UGX) <span class="text-danger">*</span></label>
              <input class="form-control" id="fund_opening" name="opening_balance" type="number" min="0" step="0.01" required>
            </div>
          </div>
          <div class="form-section"><i class="bi bi-cash-stack"></i> Custody and balance</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="fund_custodian">Custodian (Staff)</label>
              <select class="form-select" id="fund_custodian" name="custodian_staff_id">
                <option value="">No custodian</option>
                <?php foreach ($staff as $st): ?>
                <option value="<?= (int)$st['id'] ?>"><?= e($st['staff_number'] . ' - ' . $st['last_name'] . ' ' . $st['first_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="fund_account">GL Account</label>
              <select class="form-select" id="fund_account" name="account_id">
                <option value="">No account</option>
                <?php foreach ($accounts as $acct): ?>
                <option value="<?= (int)$acct['id'] ?>"><?= e($acct['account_code'] . ' - ' . $acct['account_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-section"><i class="bi bi-calendar-range"></i> Dates and status</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="fund_opened">Opened On</label>
              <input class="form-control" id="fund_opened" name="opened_on" type="date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="fund_status">Status</label>
              <select class="form-select" id="fund_status" name="status">
                <option value="open">Open</option>
                <option value="closed">Closed</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Fund</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="voucherModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Voucher</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('finance/petty-cash/save-voucher') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="fund_id" id="vouch_fund" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="vouch_amount">Amount (UGX) <span class="text-danger">*</span></label>
              <input class="form-control" id="vouch_amount" name="amount" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="vouch_spent_on">Spent On <span class="text-danger">*</span></label>
              <input class="form-control" id="vouch_spent_on" name="spent_on" maxlength="255" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="vouch_date">Expense Date <span class="text-danger">*</span></label>
              <input class="form-control" id="vouch_date" name="expense_date" type="date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="vouch_account">GL Account</label>
              <select class="form-select" id="vouch_account" name="account_id">
                <option value="">No account</option>
                <?php foreach ($accounts as $acct): ?>
                <option value="<?= (int)$acct['id'] ?>"><?= e($acct['account_code'] . ' - ' . $acct['account_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="text-muted small">Voucher will be created as "requested" and requires approval before payment.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Voucher</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('fundModal').addEventListener('show.bs.modal', function (event) {
  var trigger = event.relatedTarget;
  if (!trigger) return;
  var edit = trigger.getAttribute('data-edit');
  if (!edit) {
    document.getElementById('fundModalTitle').textContent = 'New Petty Cash Fund';
    document.getElementById('fund_id').value = '';
    this.querySelector('form').reset();
    document.getElementById('fund_opened').value = <?= json_encode(date('Y-m-d')) ?>;
    return;
  }
  var data = JSON.parse(edit);
  document.getElementById('fundModalTitle').textContent = 'Edit Petty Cash Fund';
  document.getElementById('fund_id').value = data.id || '';
  ['fund_name','opening_balance','custodian_staff_id','account_id','opened_on','status'].forEach(function (k) {
    var f = this.querySelector('[name="' + k + '"]');
    if (f) f.value = data[k] ?? '';
  }, this);
});

document.querySelectorAll('[data-bs-target="#voucherModal"], [data-toggle-modal="voucherModal"]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var fundId = this.getAttribute('data-fund-id') || this.closest('tr').querySelector('[data-fund-id]')?.getAttribute('data-fund-id');
    document.getElementById('vouch_fund').value = fundId || '';
  });
});
</script>
