<?php $minLen = password_min_length(); ?>
<div class="auth-card card border-0 shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="mb-4">
      <h1 class="h4 mb-1 fw-semibold">Reset password</h1>
      <p class="text-muted small mb-0">Choose a new password for your account.</p>
    </div>
<?php if (!empty($devLink)): ?>
    <div class="alert alert-info small app-alert"><i class="bi bi-envelope-exclamation app-alert-icon"></i><div>Email delivery is not configured. Use this reset link: <a href="<?= e($devLink) ?>" class="alert-link">Reset password</a></div></div>
<?php endif; ?>
<?php if (!$valid): ?>
    <div class="alert alert-warning app-alert"><i class="bi bi-exclamation-triangle-fill app-alert-icon"></i><div>This reset link is invalid or has expired.</div></div>
    <p class="mb-0"><a href="<?= url('forgot-password') ?>">Request a new link</a> or <a href="<?= url('login') ?>">sign in</a>.</p>
<?php else: ?>
    <form method="post" action="<?= url('reset-password') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="mb-3">
        <label class="form-label" for="rp_password">New password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input class="form-control" id="rp_password" name="password" type="password" minlength="<?= $minLen ?>" autocomplete="new-password" placeholder="At least <?= $minLen ?> characters" required>
          <button class="password-toggle" type="button" data-toggle-password="rp_password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
        </div>
        <div class="help-text"><i class="bi bi-info-circle"></i><span>Use at least <?= $minLen ?> characters.</span></div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="rp_confirm">Confirm password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
          <input class="form-control" id="rp_confirm" name="confirm" type="password" minlength="<?= $minLen ?>" autocomplete="new-password" placeholder="Re-enter the new password" required data-match="rp_password">
          <button class="password-toggle" type="button" data-toggle-password="rp_confirm" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit">Update password</button>
    </form>
<?php endif; ?>
    <p class="small text-muted mt-4 mb-0 text-center"><a href="<?= url('login') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
  </div>
</div>
