<?php
$canAdd = $canAdd ?? false;
$canEdit = $canEdit ?? false;
$canDelete = $canDelete ?? false;
?>
<div class="page-head">
  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="h4 mb-0">Chart of Accounts</h1>
      <p class="text-muted small mb-0">Income, expense, asset, liability and equity accounts used across finance modules.</p>
    </div>
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" type="button" data-toggle-modal="accountModal" data-empty><i class="bi bi-plus"></i> Add Account</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-body p-0">
<?php if (!$accounts): ?>
        <div class="text-muted text-center p-4">No accounts defined yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Code</th><th>Account Name</th><th>Type</th><th>Parent</th><th>Status</th><th style="width:14%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($accounts as $a): ?>
              <tr>
                <td><?= e($a['account_code']) ?></td>
                <td><?= e($a['account_name']) ?></td>
                <td><span class="badge text-bg-<?= $a['account_type'] === 'income' ? 'success' : ($a['account_type'] === 'expense' ? 'danger' : 'primary') ?>"><?= e(ucfirst($a['account_type'])) ?></span></td>
                <td><?= e($a['parent_name'] ?? '—') ?></td>
                <td><span class="badge text-bg-<?= (int)$a['is_active'] ? 'success' : 'secondary' ?>"><?= (int)$a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                <td class="text-nowrap">
<?php if ($canEdit): ?>
                  <button class="btn btn-outline-primary btn-sm" type="button" data-toggle-modal="accountModal" data-edit='<?= e(json_encode([
                    'id' => (int)$a['id'],
                    'account_code' => $a['account_code'],
                    'account_name' => $a['account_name'],
                    'account_type' => $a['account_type'],
                    'parent_id' => (int)$a['parent_id'] ?? 0,
                    'description' => $a['description'] ?? '',
                    'is_active' => (int)$a['is_active'],
                  ])) ?>'><i class="bi bi-pencil"></i></button>
<?php endif; ?>
<?php if ($canDelete): ?>
                  <form method="post" action="<?= url('finance/chart-of-accounts/delete') ?>" class="d-inline" data-confirm="Delete this account?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
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

<div class="modal fade" id="accountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="accountModalTitle">Add Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('finance/chart-of-accounts/save') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="account_id" value="">
        <div class="modal-body">
          <div class="form-section"><i class="bi bi-person-vcard"></i> Account identity</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="coa_account_code">Account Code <span class="text-danger">*</span></label>
              <input class="form-control" id="coa_account_code" name="account_code" maxlength="20" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="coa_account_name">Account Name <span class="text-danger">*</span></label>
              <input class="form-control" id="coa_account_name" name="account_name" maxlength="140" required>
            </div>
          </div>
          <div class="form-section"><i class="bi bi-tags"></i> Classification</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="coa_account_type">Account Type <span class="text-danger">*</span></label>
              <select class="form-select" id="coa_account_type" name="account_type" required>
                <option value="income">Income</option>
                <option value="expense">Expense</option>
                <option value="asset">Asset</option>
                <option value="liability">Liability</option>
                <option value="equity">Equity</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="coa_parent_id">Parent Account</label>
              <select class="form-select" id="coa_parent_id" name="parent_id">
                <option value="0">None (top level)</option>
              </select>
            </div>
          </div>
          <div class="form-section"><i class="bi bi-sliders"></i> Opening balance</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="coa_description">Description</label>
              <input class="form-control" id="coa_description" name="description" maxlength="255">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="coa_is_active" checked>
                <label class="form-check-label" for="coa_is_active">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('accountModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (event) {
    var trigger = event.relatedTarget;
    if (!trigger) return;
    var edit = trigger.getAttribute('data-edit');
    if (!edit) {
      document.getElementById('accountModalTitle').textContent = 'Add Account';
      document.getElementById('account_id').value = '';
      modal.querySelector('form').reset();
      var cb = modal.querySelector('[name="is_active"]');
      if (cb) cb.checked = true;
      return;
    }
    var data = JSON.parse(edit);
    document.getElementById('accountModalTitle').textContent = 'Edit Account';
    document.getElementById('account_id').value = data.id || '';
    ['account_code','account_name','account_type','parent_id','description'].forEach(function (k) {
      var f = modal.querySelector('[name="' + k + '"]');
      if (f) f.value = data[k] ?? '';
    });
    var cb = modal.querySelector('[name="is_active"]');
    if (cb) cb.checked = data.is_active == 1;
  });

  // Load parent accounts
  fetch('<?= url('finance/chart-of-accounts/list') ?>')
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var sel = document.getElementById('coa_parent_id');
      if (!sel || !data) return;
      (data || []).forEach(function (a) {
        var o = document.createElement('option');
        o.value = a.id;
        o.textContent = a.account_code + ' - ' + a.account_name;
        sel.appendChild(o);
      });
    });
});
</script>
