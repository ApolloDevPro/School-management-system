<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Online payment integration</h1>
    <p class="text-muted small mb-0">Configure gateway metadata and review manually entered sandbox transaction logs.</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-primary btn-sm" href="<?= url('finance') ?>">Finance &amp; fees</a>
    <a class="btn btn-outline-primary btn-sm" href="<?= url('compliance') ?>">Compliance &amp; grants</a>
  </div>
</div>
<div class="alert alert-info">
  <strong>Safe mode:</strong> this module makes no provider/network requests and does not store API credentials.
  Transaction status changes here are monitoring records only; they never post payments or issue receipts.
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card app-card h-100">
      <div class="card-header">Gateway configuration</div>
      <div class="card-body">
        <form method="post" action="<?= url('finance/online/gateway') ?>">
          <?= csrf_field() ?>
          <div class="row g-3 mb-3">
            <div class="col-md-5">
              <label class="form-label" for="gateway_code">Provider code</label>
              <input class="form-control" id="gateway_code" name="provider_code" maxlength="40" pattern="[A-Za-z0-9_-]+" required placeholder="e.g. SCHOOLPAY">
            </div>
            <div class="col-md-7">
              <label class="form-label" for="gateway_name">Provider name</label>
              <input class="form-control" id="gateway_name" name="provider_name" maxlength="100" required>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label" for="gateway_type">Provider type</label>
              <select class="form-select" id="gateway_type" name="provider_type" required>
<?php foreach (['mobile_money' => 'Mobile money', 'bank' => 'Bank', 'card' => 'Card', 'other' => 'Other'] as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
<?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="gateway_currency">Currency</label>
              <input class="form-control" id="gateway_currency" name="currency" maxlength="10" value="UGX" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="gateway_env">Environment</label>
              <select class="form-select" id="gateway_env" name="environment">
                <option value="sandbox" selected>Sandbox</option>
                <option value="production">Production (not connected)</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="gateway_notes">Notes</label>
            <input class="form-control" id="gateway_notes" name="notes" maxlength="255">
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" id="gateway_active" name="is_active" type="checkbox" checked>
            <label class="form-check-label" for="gateway_active">Enable in local configuration</label>
          </div>
          <button class="btn btn-primary" type="submit">Save configuration</button>
          <p class="small text-muted mt-2 mb-0">Do not enter keys, passwords, merchant secrets, callback URLs or private credentials here.</p>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card app-card h-100">
      <div class="card-header">Configured gateways</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Provider</th><th>Type / environment</th><th>Currency</th><th>State</th></tr></thead>
          <tbody>
<?php if (!$gateways): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No gateways configured.</td></tr>
<?php else: foreach ($gateways as $gateway): ?>
            <tr>
              <td><?= e($gateway['provider_name']) ?><div class="small text-muted"><?= e($gateway['provider_code']) ?></div></td>
              <td><?= e(ucwords(str_replace('_', ' ', $gateway['provider_type']))) ?><div class="small text-muted"><?= e(ucfirst($gateway['environment'])) ?></div></td>
              <td><?= e($gateway['currency']) ?></td>
              <td>
                <form method="post" action="<?= url('finance/online/gateway-toggle') ?>" class="d-flex align-items-center gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="gateway_id" value="<?= (int)$gateway['id'] ?>">
                  <input type="hidden" name="active" value="<?= (int)!$gateway['is_active'] ?>">
                  <span class="badge text-bg-<?= $gateway['is_active'] ? 'success' : 'secondary' ?>"><?= $gateway['is_active'] ? 'Enabled' : 'Disabled' ?></span>
                  <button class="btn btn-outline-secondary btn-sm" type="submit"><?= $gateway['is_active'] ? 'Disable' : 'Enable' ?></button>
                </form>
              </td>
            </tr>
<?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card app-card mb-4">
  <div class="card-header">Enter sandbox transaction log (no network call)</div>
  <div class="card-body">
<?php $activeGateways = array_filter($gateways, static fn (array $gateway): bool => (int)$gateway['is_active'] === 1); ?>
<?php if (!$activeGateways): ?>
    <div class="alert alert-secondary mb-0">Enable a gateway configuration before recording a test transaction.</div>
<?php else: ?>
    <form method="post" action="<?= url('finance/online/transaction') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-2">
          <label class="form-label" for="txn_gateway">Gateway</label>
          <select class="form-select" id="txn_gateway" name="gateway_config_id" required>
<?php foreach ($activeGateways as $gateway): ?>
            <option value="<?= (int)$gateway['id'] ?>"><?= e($gateway['provider_name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="txn_status">Initial state</label>
          <select class="form-select" id="txn_status" name="status">
            <option value="initiated">Initiated</option><option value="pending">Pending</option><option value="failed">Failed</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="txn_amount">Amount</label>
          <input class="form-control" id="txn_amount" name="amount" type="number" min="0.01" step="0.01" required>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="txn_currency">Currency</label>
          <input class="form-control" id="txn_currency" name="currency" maxlength="10" value="UGX" required>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="txn_provider_ref">Provider transaction ID</label>
          <input class="form-control" id="txn_provider_ref" name="provider_txn_id" maxlength="120">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="txn_payer">Payer name</label>
          <input class="form-control" id="txn_payer" name="payer_name" maxlength="160">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="txn_invoice">Invoice (optional)</label>
          <select class="form-select" id="txn_invoice" name="invoice_id">
            <option value="">Unmatched</option>
<?php foreach ($invoices as $invoice): ?>
            <option value="<?= (int)$invoice['id'] ?>"><?= e($invoice['invoice_number'] . ' â€” ' . $invoice['last_name'] . ' ' . $invoice['first_name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="txn_student">Student if unmatched</label>
          <select class="form-select" id="txn_student" name="student_id">
            <option value="">Not matched</option>
<?php foreach ($students as $student): ?>
            <option value="<?= (int)$student['id'] ?>"><?= e($student['admission_number'] . ' â€” ' . $student['last_name'] . ' ' . $student['first_name']) ?></option>
<?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="txn_phone">Payer phone</label>
          <input class="form-control" id="txn_phone" name="payer_msisdn" maxlength="40">
        </div>
        <div class="col-md-3">
          <label class="form-label" for="txn_failure">Failure reason (if applicable)</label>
          <input class="form-control" id="txn_failure" name="failure_reason" maxlength="255">
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">Record transaction log</button>
    </form>
<?php endif; ?>
  </div>
</div>

<div class="card app-card">
  <div class="card-header">Gateway transaction monitoring</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Gateway transaction</th><th>Student / invoice</th><th>Amount</th><th>State / timestamp</th><th>Monitoring</th></tr></thead>
      <tbody>
<?php if (!$transactions): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No gateway transaction logs are available.</td></tr>
<?php else: foreach ($transactions as $transaction): ?>
        <tr>
          <td><?= e($transaction['provider_name']) ?><div class="small text-muted"><?= e($transaction['provider_txn_id'] ?: 'No provider ID') ?></div></td>
          <td><?= e(trim(($transaction['last_name'] ?? '') . ' ' . ($transaction['first_name'] ?? '')) ?: 'Unmatched') ?><div class="small text-muted"><?= e($transaction['invoice_number'] ?? '') ?></div></td>
          <td><?= e($transaction['currency']) ?> <?= number_format((float)$transaction['amount'], 2) ?></td>
          <td><span class="badge text-bg-<?= $transaction['status'] === 'success' ? 'success' : (in_array($transaction['status'], ['failed', 'reversed'], true) ? 'danger' : 'warning') ?>"><?= e(ucfirst($transaction['status'])) ?></span><div class="small text-muted"><?= e($transaction['initiated_at']) ?></div></td>
          <td>
            <form method="post" action="<?= url('finance/online/transaction-update') ?>" class="d-flex flex-wrap gap-1">
              <?= csrf_field() ?>
              <input type="hidden" name="transaction_id" value="<?= (int)$transaction['id'] ?>">
              <select class="form-select form-select-sm" name="status" aria-label="Monitoring status">
<?php foreach (['initiated', 'pending', 'success', 'failed', 'reversed', 'duplicate', 'expired'] as $status): ?>
                <option value="<?= e($status) ?>" <?= $transaction['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
<?php endforeach; ?>
              </select>
              <input class="form-control form-control-sm" name="resolution_note" maxlength="255" value="<?= e($transaction['resolution_note']) ?>" placeholder="Staff note" aria-label="Resolution note">
              <button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
            </form>
            <?php if ($transaction['failure_reason']): ?><div class="small text-danger"><?= e($transaction['failure_reason']) ?></div><?php endif; ?>
          </td>
        </tr>
<?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
