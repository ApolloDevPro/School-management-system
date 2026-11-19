<?php $minLen = password_min_length(); ?>
<div class="auth-wrap">
  <div class="auth-card card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
      <div class="mb-4">
        <h1 class="h4 mb-1 fw-semibold">Change password</h1>
        <p class="text-muted small mb-0"><?= $mustChange ? 'For your security, set a new password before continuing.' : 'Update the password for your account.' ?></p>
      </div>
      <form method="post" action="<?= url('change-password/save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="cp_current">Current password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input class="form-control" id="cp_current" name="current_password" type="password" autocomplete="current-password" placeholder="Your current password" required>
            <button class="password-toggle" type="button" data-toggle-password="cp_current" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <span class="invalid-feedback d-block" data-error-for="current_password"></span>
        </div>
        <div class="mb-3">
          <label class="form-label" for="cp_new">New password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-key"></i></span>
            <input class="form-control" id="cp_new" name="password" type="password" minlength="<?= $minLen ?>" autocomplete="new-password" placeholder="At least <?= $minLen ?> characters" required>
            <button class="password-toggle" type="button" data-toggle-password="cp_new" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <div class="help-text"><i class="bi bi-info-circle"></i><span>Use at least <?= $minLen ?> characters.</span></div>
          <span class="invalid-feedback d-block" data-error-for="password"></span>
        </div>
        <div class="mb-4">
          <label class="form-label" for="cp_confirm">Confirm new password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
            <input class="form-control" id="cp_confirm" name="confirm" type="password" minlength="<?= $minLen ?>" autocomplete="new-password" placeholder="Re-enter the new password" required data-match="cp_new">
            <button class="password-toggle" type="button" data-toggle-password="cp_confirm" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
          </div>
          <span class="invalid-feedback d-block" data-error-for="confirm"></span>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Update password</button>
      </form>
      <?php if (!$mustChange): ?>
      <p class="small text-muted mt-4 mb-0 text-center"><a href="<?= url(Auth::homeRoute()) ?>"><i class="bi bi-arrow-left me-1"></i> Back to <?= Auth::homeRoute() === 'portal' ? 'the portal' : 'the dashboard' ?></a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
