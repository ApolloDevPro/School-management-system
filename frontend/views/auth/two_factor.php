<div class="auth-card card border-0 shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="mb-4">
      <h1 class="h4 mb-1 fw-semibold">Two-factor verification</h1>
      <p class="text-muted small mb-0">Enter the 6-digit code from your authenticator app for <strong><?= e($username) ?></strong>.</p>
    </div>
    <form method="post" action="<?= url('two-factor/verify') ?>">
      <?= csrf_field() ?>
      <div class="mb-4">
        <label class="form-label" for="tf_code">Verification code</label>
        <input class="form-control form-control-lg text-center" id="tf_code" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" aria-describedby="tf_hint" required autofocus>
        <div class="help-text justify-content-center" id="tf_hint"><i class="bi bi-phone"></i><span>Open your authenticator app and enter the current 6-digit code. It changes every 30 seconds.</span></div>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-shield-check me-1"></i> Verify</button>
    </form>
    <form method="post" action="<?= url('logout') ?>" class="mt-3 mb-0 text-center">
      <?= csrf_field() ?>
      <button class="btn btn-link btn-sm text-muted p-0" type="submit">Cancel and sign out</button>
    </form>
  </div>
</div>