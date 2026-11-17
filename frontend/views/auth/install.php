<div class="auth-card card border-0 shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="mb-4">
      <h1 class="h4 mb-1 fw-semibold">First-time setup</h1>
      <p class="text-muted small mb-0">Set the sign-in password for an account that has none yet.</p>
    </div>

    <form method="post" action="<?= url('install') ?>">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="install_username">Account</label>
        <select class="form-select form-select-lg" id="install_username" name="username" required>
<?php foreach ($pending as $row): ?>
          <option value="<?= e($row['username']) ?>" <?= ($_POST['username'] ?? '') === $row['username'] ? 'selected' : '' ?>>
            <?= e($row['username']) ?> &mdash; <?= e($row['full_name']) ?>
          </option>
<?php endforeach; ?>
        </select>
        <div class="invalid-feedback" data-error-for="username"></div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="install_password">New password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input class="form-control" id="install_password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
        </div>
        <div class="invalid-feedback" data-error-for="password"></div>
        <div class="form-text">Use at least 8 characters.</div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="install_confirm">Confirm password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
          <input class="form-control" id="install_confirm" name="confirm" type="password" minlength="8" autocomplete="new-password" placeholder="Re-enter password" required>
        </div>
        <div class="invalid-feedback" data-error-for="confirm"></div>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-check2-circle me-1"></i> Set password</button>
    </form>

    <p class="small text-muted mt-4 mb-0 text-center"><a href="<?= url('login') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
  </div>
</div>
