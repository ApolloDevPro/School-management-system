<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Bank Reconciliation</h1>
    <p class="text-muted small mb-0">Match bank statement transactions to system payments and expenses</p>
  </div>
  <div class="text-end">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal"><i class="bi bi-plus"></i> Add bank account</button>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('finance/bank-reconciliation') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Bank account</label>
        <select class="form-select" name="bank_account_id">
          <option value="0">-- Select an account --</option>
<?php foreach ($accounts as $acct): ?>
          <option value="<?= (int)$acct['id'] ?>" <?= $accountId === (int)$acct['id'] ? 'selected' : '' ?>>
            <?= e($acct['bank_name']) ?> — <?= e($acct['account_name']) ?> (<?= e($acct['account_number']) ?>)
          </option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Period start</label>
        <input class="form-control" type="date" name="period_start" value="<?= e($periodStart) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Period end</label>
        <input class="form-control" type="date" name="period_end" value="<?= e($periodEnd) ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<?php if ($accountId > 0 && $periodStart !== '' && $periodEnd !== ''): ?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card app-card mb-3">
      <div class="card-body">
        <h6 class="card-title">Unmatched bank transactions</h6>
        <p class="small text-muted mb-2">Statement items not yet matched to system records.</p>
        <?php if ($unmatchedTransactions): ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Description</th><th>Deposit</th><th>Withdrawal</th><th>Ref</th></tr></thead>
              <tbody>
<?php foreach ($unmatchedTransactions as $txn): ?>
              <tr>
                <td><?= e($txn['transaction_date']) ?></td>
                <td><?= e($txn['description']) ?></td>
                <td><?= (float)$txn['deposit'] > 0 ? number_format((float)$txn['deposit'], 2) : '' ?></td>
                <td><?= (float)$txn['withdrawal'] > 0 ? number_format((float)$txn['withdrawal'], 2) : '' ?></td>
                <td><?= e($txn['reference'] ?? '-') ?></td>
              </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0">No unmatched bank transactions.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card app-card mb-3">
      <div class="card-body">
        <h6 class="card-title">Unmatched verified payments</h6>
        <p class="small text-muted mb-2">Bank payments recorded in SSMS not yet on the statement.</p>
        <?php if ($unmatchedPayments): ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Reference</th><th>Payer</th><th>Amount</th></tr></thead>
              <tbody>
<?php foreach ($unmatchedPayments as $pay): ?>
              <tr>
                <td><?= e($pay['payment_date']) ?></td>
                <td><?= e($pay['payment_reference']) ?></td>
                <td><?= e($pay['payer_name'] ?? ($pay['first_name'] . ' ' . $pay['last_name'])) ?></td>
                <td><?= number_format((float)$pay['amount'], 2) ?></td>
              </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0">No unmatched verified payments.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card app-card mb-3">
      <div class="card-body">
        <h6 class="card-title">Unmatched approved expenses</h6>
        <p class="small text-muted mb-2">Approved bank expenses not yet on the statement.</p>
        <?php if ($unmatchedExpenses): ?>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Date</th><th>Payee</th><th>Amount</th></tr></thead>
              <tbody>
<?php foreach ($unmatchedExpenses as $exp): ?>
              <tr>
                <td><?= e($exp['expense_date']) ?></td>
                <td><?= e($exp['payee']) ?></td>
                <td><?= number_format((float)$exp['amount'], 2) ?></td>
              </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="text-muted small mb-0">No unmatched approved expenses.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <h6 class="card-title">Complete reconciliation</h6>
    <form method="post" action="<?= url('finance/bank-reconcile') ?>" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="bank_account_id" value="<?= (int)$accountId ?>">
      <input type="hidden" name="period_start" value="<?= e($periodStart) ?>">
      <input type="hidden" name="period_end" value="<?= e($periodEnd) ?>">
      <div class="col-md-4">
        <label class="form-label">Statement balance</label>
        <input class="form-control" type="number" step="0.01" name="statement_balance" required min="0">
      </div>
      <div class="col-md-4">
        <label class="form-label">Notes</label>
        <textarea class="form-control" name="notes" rows="2"></textarea>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button class="btn btn-success w-100">Complete reconciliation</button>
      </div>
    </form>
  </div>
</div>

<?php if ($reconciliations): ?>
<div class="card app-card mb-3">
  <div class="card-body">
    <h6 class="card-title">Reconciliation history</h6>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Period</th>
            <th>Statement balance</th>
            <th>Book balance</th>
            <th>Difference</th>
            <th>Unmatched</th>
            <th>Status</th>
            <th>Reconciled by</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($reconciliations as $rec): ?>
          <tr>
            <td><?= e($rec['period_start']) ?> to <?= e($rec['period_end']) ?></td>
            <td><?= number_format((float)$rec['statement_balance'], 2) ?></td>
            <td><?= number_format((float)$rec['book_balance'], 2) ?></td>
            <td><?= number_format((float)$rec['difference'], 2) ?></td>
            <td><?= (int)$rec['unmatched_count'] ?></td>
            <td><span class="badge bg-<?= $rec['status'] === 'completed' ? 'success' : ($rec['status'] === 'disputed' ? 'danger' : 'warning') ?>"><?= e(ucfirst($rec['status'])) ?></span></td>
            <td><?= e($rec['reconciled_by_name'] ?? '-') ?></td>
            <td><?= e($rec['reconciled_at'] ?? '-') ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="bankAccountModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('finance/bank-account/save') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Add bank account</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="0">
          <div class="mb-3">
            <label class="form-label">Bank name</label>
            <input class="form-control" name="bank_name" required maxlength="100">
          </div>
          <div class="mb-3">
            <label class="form-label">Account name</label>
            <input class="form-control" name="account_name" required maxlength="140">
          </div>
          <div class="mb-3">
            <label class="form-label">Account number</label>
            <input class="form-control" name="account_number" required maxlength="40">
          </div>
          <div class="mb-3">
            <label class="form-label">Branch name</label>
            <input class="form-control" name="branch_name" maxlength="80">
          </div>
          <div class="mb-3">
            <label class="form-label">SWIFT code</label>
            <input class="form-control" name="swift_code" maxlength="20">
          </div>
          <div class="mb-3">
            <label class="form-label">Currency</label>
            <select class="form-select" name="currency">
              <option value="UGX">UGX</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Opening balance</label>
              <input class="form-control" type="number" step="0.01" name="opening_balance" min="0" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Current balance</label>
              <input class="form-control" type="number" step="0.01" name="current_balance" min="0" value="0">
            </div>
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
            <label class="form-check-label" for="is_default">Set as default account</label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="bankTransactionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('finance/bank-transaction/save') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Add bank transaction</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="bank_account_id" value="<?= (int)$accountId ?>">
          <div class="mb-3">
            <label class="form-label">Transaction date</label>
            <input class="form-control" type="date" name="transaction_date" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Value date</label>
            <input class="form-control" type="date" name="value_date">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input class="form-control" name="description" required maxlength="255">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Deposit</label>
              <input class="form-control" type="number" step="0.01" name="deposit" min="0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Withdrawal</label>
              <input class="form-control" type="number" step="0.01" name="withdrawal" min="0">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reference</label>
            <input class="form-control" name="reference" maxlength="80">
          </div>
          <div class="mb-3">
            <label class="form-label">Source</label>
            <select class="form-select" name="source">
              <option value="manual">Manual entry</option>
              <option value="import">Bank import</option>
              <option value="system">System generated</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save transaction</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($accountId > 0): ?>
<button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bankTransactionModal" style="margin-top:1rem;"><i class="bi bi-plus-circle"></i> Add bank transaction</button>
<?php endif; ?>
