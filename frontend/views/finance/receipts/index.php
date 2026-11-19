<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Receipts</h1>
    <p class="text-muted small mb-0">All issued payment receipts for students.</p>
  </div>
  <?php if ($canAdd ?? false): ?>
  <a class="btn btn-outline-secondary" href="<?= url('finance') ?>"><i class="bi bi-arrow-left me-1"></i> Back to finance</a>
  <?php endif; ?>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-table>
        <thead>
          <tr>
            <th>Receipt No.</th>
            <th>Date</th>
            <th>Student</th>
            <th>Admission No.</th>
            <th class="text-right">Amount</th>
            <th class="text-center">Method</th>
            <th class="text-center">Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$receipts): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No receipts have been issued.</td></tr>
          <?php else: foreach ($receipts as $r): ?>
          <tr>
            <td>
              <?= (int)$r['is_void'] === 1 ? '<span class="text-danger"><strike>' . e($r['receipt_number']) . '</strike></span>' : '<strong>' . e($r['receipt_number']) . '</strong>' ?>
              <div class="small text-muted"><?= e(date('d M Y', strtotime($r['issued_at'] ?? $r['created_at'] ?? ''))) ?></div>
            </td>
            <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td><?= e($r['admission_number']) ?></td>
            <td class="text-right"><?= number_format((float)$r['amount'], 2) ?> <?= e(($school['currency_symbol'] ?? 'Ugx')) ?></td>
            <td class="text-center"><?= e(ucwords(str_replace('_', ' ', $r['channel'] ?? 'cash'))) ?></td>
            <td class="text-center">
              <?php if ((int)$r['is_void'] === 1): ?>
                <span class="badge text-bg-danger">Void</span>
              <?php else: ?>
                <span class="badge text-bg-<?= in_array($r['delivery_status'] ?? 'printed', ['printed','emailed','sms','portal'], true) ? 'success' : 'secondary' ?>">
                  <?= ucfirst($r['delivery_status'] ?? 'printed') ?>
                </span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="<?= url('finance/receipt-pdf', ['id' => (int)$r['id']]) ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
              <?php if ((int)$r['is_void'] === 0): ?>
                <button class="btn btn-sm btn-outline-primary" data-toggle-modal="reprintModal" data-id="<?= (int)$r['id'] ?>" data-number="<?= e($r['receipt_number']) ?>"><i class="bi bi-printer"></i> Reprint</button>
                <button class="btn btn-sm btn-outline-success" data-toggle-modal="emailModal" data-id="<?= (int)$r['id'] ?>" data-student="<?= e($r['first_name'] . ' ' . $r['last_name']) ?>"><i class="bi bi-envelope"></i> Email</button>
                <button class="btn btn-sm btn-outline-info" data-toggle-modal="smsModal" data-id="<?= (int)$r['id'] ?>" data-student="<?= e($r['first_name'] . ' ' . $r['last_name']) ?>"><i class="bi bi-phone"></i> SMS</button>
                <button class="btn btn-sm btn-outline-danger" data-toggle-modal="voidModal" data-id="<?= (int)$r['id'] ?>" data-number="<?= e($r['receipt_number']) ?>"><i class="bi bi-trash"></i> Void</button>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Reprint modal -->
<div class="modal fade" id="reprintModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reprint Receipt</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= url('finance/receipts/reprint') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="reprintId">
        <div class="modal-body">
          <p>Reprint receipt <strong id="reprintNumber"></strong>?</p>
          <p class="small text-muted">This will increment the printed count and mark delivery as printed.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Reprint</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Email modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Email Receipt</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= url('finance/receipts/email') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="emailId">
        <div class="modal-body">
          <p>Send receipt <strong id="emailNumber"></strong> to <strong id="emailStudent"></strong> via email?</p>
          <p class="small text-muted">The guardian email on file will be used.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Send Email</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SMS modal -->
<div class="modal fade" id="smsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Send Receipt via SMS</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= url('finance/receipts/sms') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="smsId">
        <div class="modal-body">
          <p>Send receipt <strong id="smsNumber"></strong> to <strong id="smsStudent"></strong> via SMS?</p>
          <p class="small text-muted">The guardian phone on file will be used.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary" type="submit">Send SMS</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Void modal -->
<div class="modal fade" id="voidModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Void Receipt</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="<?= url('finance/receipts/void') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="voidId">
        <div class="modal-body">
          <p>Void receipt <strong id="voidNumber"></strong>?</p>
          <div class="mb-3">
            <label class="form-label">Void reason <span class="text-danger">*</span></label>
            <textarea class="form-control" name="reason" rows="3" required placeholder="Why is this receipt being voided?"></textarea>
            <div class="invalid-feedback d-block" data-error-for="reason"></div>
          </div>
          <p class="small text-muted">Voiding a receipt marks it as invalid and updates its delivery status to void.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" type="submit">Void Receipt</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-toggle-modal]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var modalId = this.getAttribute('data-toggle-modal');
    var id = this.getAttribute('data-id');
    var number = this.getAttribute('data-number');
    var student = this.getAttribute('data-student');

    var modal = document.getElementById(modalId);
    if (!modal) return;

    var idInput = modal.querySelector('input[name="id"]');
    if (idInput) idInput.value = id;

    var numEl = modal.querySelector('#' + modalId.replace('Modal', '') + 'Number');
    if (numEl) numEl.textContent = number;

    var stuEl = modal.querySelector('#' + modalId.replace('Modal', '') + 'Student');
    if (stuEl) stuEl.textContent = student;

    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();
  });
});
</script>
