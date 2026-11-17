<div class="auth-card card border-0 shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="mb-4">
      <h1 class="h4 mb-1 fw-semibold">Reset password</h1>
      <p class="text-muted small mb-0">Choose a new password for your account.</p>
    </div>
<?php if (!empty($devLink)): ?>
    <div class="alert alert-info small">Email delivery is not configured. Use this reset link: <a href="<?= e($devLink) ?>" class="alert-link">Reset password</a></div>
<?php endif; ?>
<?php if (!$valid): ?>
    <div class="alert alert-warning">This reset link is invalid or has expired.</div>
    <p class="mb-0"><a href="<?= url('forgot-password') ?>">Request a new link</a> or <a href="<?= url('login') ?>">sign in</a>.</p>
<?php else: ?>
    <form method="post" action="<?= url('reset-password') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="mb-3">
        <label class="form-label" for="rp_password">New password</label>
        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input class="form-control" id="rp_password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
        <div class="form-text">At least 8 characters.</div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="rp_confirm">Confirm password</label>
        <div class="input-group"><span class="input-group-text"><i class="bi bi-shield-check"></i></span><input class="form-control" id="rp_confirm" name="confirm" type="password" minlength="8" autocomplete="new-password" required></div>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit">Update password</button>
    </form>
<?php endif; ?>
  </div>
</div>