<div class="auth-wrap">
  <div class="auth-card card border-0 shadow-sm">
    <div class="card-body p-4 p-md-5">
      <div class="mb-4">
        <h1 class="h4 mb-1 fw-semibold">Change password</h1>
        <p class="text-muted small mb-0"><?= $mustChange ? 'You must change your password before continuing.' : 'Update your account password.' ?></p>
      </div>
      <form method="post" action="<?= url('change-password/save') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="cp_current">Current password</label>
          <input class="form-control" id="cp_current" name="current_password" type="password" autocomplete="current-password" required>
          <span class="invalid-feedback d-block" data-error-for="current_password"></span>
        </div>
        <div class="mb-3">
          <label class="form-label" for="cp_new">New password</label>
          <input class="form-control" id="cp_new" name="password" type="password" minlength="8" autocomplete="new-password" required>
          <div class="form-text">At least 8 characters.</div>
          <span class="invalid-feedback d-block" data-error-for="password"></span>
        </div>
        <div class="mb-4">
          <label class="form-label" for="cp_confirm">Confirm new password</label>
          <input class="form-control" id="cp_confirm" name="confirm" type="password" minlength="8" autocomplete="new-password" required>
          <span class="invalid-feedback d-block" data-error-for="confirm"></span>
        </div>
        <button class="btn btn-primary btn-lg w-100" type="submit">Update password</button>
      </form>
      <?php if (!$mustChange): ?>
      <p class="small text-muted mt-4 mb-0 text-center"><a href="<?= url('dashboard') ?>"><i class="bi bi-arrow-left me-1"></i> Back to dashboard</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
