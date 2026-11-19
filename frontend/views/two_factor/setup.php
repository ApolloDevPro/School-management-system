<div class="page-head d-flex justify-content-between align-items-start">
  <div>
    <h1 class="h4 mb-0">Two-Factor Authentication</h1>
    <p class="text-muted small mb-0">Add a second verification step to your account using an authenticator app (Google Authenticator, Authy, etc.).</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= url('security') ?>"><i class="bi bi-arrow-left me-1"></i> Back to security</a>
</div>

<div class="row g-3">
  <div class="col-lg-6 col-xl-5">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Authenticator Setup</span>
      </div>
      <div class="card-body">
        <div class="text-center mb-4">
          <div id="qrBox" class="d-inline-block mb-2" data-otpauth="<?= e($otpAuthUrl) ?>" style="min-width:200px;min-height:200px;border:1px solid #e2e8f0;padding:8px;background:#fff;border-radius:8px"></div>
          <div class="text-muted small">Scan this QR code with your authenticator app</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Setup Key</label>
          <div class="input-group">
            <input class="form-control font-monospace" value="<?= e($secret) ?>" readonly>
            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)"><i class="bi bi-clipboard"></i></button>
          </div>
          <div class="form-text">Enter this key manually if you cannot scan the QR code.</div>
        </div>
        <form method="post" action="<?= url('security/2fa-verify-setup') ?>" data-ajax>
          <?= csrf_field() ?>
          <input type="hidden" name="secret" value="<?= e($secret) ?>">
          <div class="mb-3">
            <label class="form-label" for="tf_code">Verification Code <span class="text-danger">*</span></label>
            <input class="form-control" id="tf_code" name="code" maxlength="6" placeholder="6-digit code" required>
            <div class="form-text">Enter the 6-digit code from your authenticator app to verify setup.</div>
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit">Verify &amp; Enable 2FA</button>
            <a class="btn btn-outline-secondary" href="<?= url('security') ?>">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6 col-xl-5 offset-xl-2">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>SMS / Email Codes</span>
      </div>
      <div class="card-body">
        <p class="text-muted small">Receive one-time verification codes via SMS or email as an alternative second factor.</p>
        <div class="d-grid gap-2 mb-3">
          <button class="btn btn-outline-primary" type="button" data-toggle-modal="sendCodeModal" data-empty><i class="bi bi-phone"></i> Send SMS Code</button>
          <button class="btn btn-outline-primary" type="button" data-toggle-modal="sendCodeModal" data-empty><i class="bi bi-envelope"></i> Send Email Code</button>
        </div>
        <div class="alert alert-warning border-0 bg-warning bg-opacity-10">
          <i class="bi bi-exclamation-triangle me-1"></i>
          <strong>SMS/Email gateway not configured.</strong> Codes will only be delivered if SMS and SMTP settings are filled in under Settings.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="sendCodeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Verify Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('security/2fa-verify-code') ?>" data-ajax>
        <?= csrf_field() ?>
        <input type="hidden" name="channel" id="modal_channel" value="sms">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" id="modal_channel_label">Channel</label>
            <input class="form-control" name="code" maxlength="6" placeholder="6-digit code" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Verify Code</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
  var box = document.getElementById('qrBox');
  if (!box) { return; }
  if (window.QRCode) {
    new QRCode(box, { text: box.getAttribute('data-otpauth'), width: 200, height: 200 });
  } else {
    box.textContent = 'QR library unavailable - enter the setup key manually.';
  }
})();
</script>
<script>
document.querySelectorAll('[data-toggle-modal="sendCodeModal"]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var isSms = this.classList.contains('bi-phone') || this.textContent.indexOf('SMS') >= 0;
    document.getElementById('modal_channel').value = isSms ? 'sms' : 'email';
    document.getElementById('modal_channel_label').textContent = isSms ? 'SMS Code' : 'Email Code';
  });
});
</script>
