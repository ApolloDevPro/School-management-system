<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Student Wallets</h1>
    <p class="text-muted small mb-0">Deposit money into student accounts for canteen and shop purchases</p>
  </div>
  <div class="text-end">
    <?php if ($canAdd): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#depositModal"><i class="bi bi-plus"></i> Deposit</button>
    <?php endif; ?>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('canteen/wallet') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Select student</label>
        <select class="form-select" name="student_id" data-submit-on-change>
          <option value="0">-- Select a student --</option>
<?php foreach ($students as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $studentId === (int)$s['id'] ? 'selected' : '' ?>>
            <?= e($s['admission_number']) ?> — <?= e($s['first_name'] . ' ' . $s['last_name']) ?>
            <?php if (isset($s['balance'])): ?> (Balance: <?= number_format((float)$s['balance'], 2) ?>)
            <?php endif; ?>
          </option>
<?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($studentId > 0 && $student): ?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card app-card mb-3">
      <div class="card-body text-center">
        <h3 class="text-primary mb-2"><?= number_format((float)($wallet['balance'] ?? 0), 2) ?></h3>
        <p class="text-muted small mb-0">Wallet balance</p>
        <p class="small text-muted">Wallet: <?= e($wallet['wallet_number'] ?? '—') ?></p>
        <p class="small text-muted">Status: <span class="badge bg-<?= ($wallet['status'] ?? '') === 'active' ? 'success' : 'warning' ?>"><?= e(ucfirst($wallet['status'] ?? '—')) ?></span></p>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card app-card mb-3">
      <div class="card-header">Wallet transactions for <?= e($student['first_name'] . ' ' . $student['last_name']) ?> (<?= e($student['admission_number']) ?>)</div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance before</th><th>Balance after</th><th>Reference</th><th>Notes</th></tr>
            </thead>
            <tbody>
<?php if (!$transactions): ?><tr><td colspan="7" class="text-center text-muted py-4">No transactions found.</td></tr><?php endif; ?>
<?php foreach ($transactions as $txn): ?>
              <tr>
                <td><?= e($txn['created_at']) ?></td>
                <td><span class="badge bg-<?= $txn['transaction_type'] === 'deposit' ? 'success' : ($txn['transaction_type'] === 'purchase' ? 'danger' : 'secondary') ?>"><?= e(ucfirst($txn['transaction_type'])) ?></span></td>
                <td><?= number_format((float)$txn['amount'], 2) ?></td>
                <td><?= number_format((float)($txn['balance_before'] ?? 0), 2) ?></td>
                <td><?= number_format((float)($txn['balance_after'] ?? 0), 2) ?></td>
                <td><?= e($txn['reference'] ?? '—') ?></td>
                <td><?= e($txn['notes'] ?? '—') ?></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canAdd): ?>
<div class="modal fade" id="depositModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('canteen/wallet/deposit') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Deposit to student wallet</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Student</label>
            <select class="form-select" name="student_id" required>
              <option value="">-- Select student --</option>
<?php foreach ($students as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['admission_number']) ?> — <?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
<?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Amount (UGX)</label>
            <input class="form-control" type="number" step="0.01" name="amount" required min="0">
          </div>
          <div class="mb-3">
            <label class="form-label">Reference</label>
            <input class="form-control" name="reference" maxlength="80" placeholder="e.g. Parent deposit">
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="2" maxlength="255"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Record deposit</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
