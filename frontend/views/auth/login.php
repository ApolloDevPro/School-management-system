<?php $logo = school_row()['logo_path'] ?? ''; ?>
<div class="auth-card card border-0 shadow-lg">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <?php if ($logo): ?>
        <img src="<?= e(asset($logo)) ?>" alt="School logo" class="brand-mark brand-logo mx-auto mb-3" style="width:56px;height:56px;border-radius:14px;">
      <?php else: ?>
        <div class="brand-mark mx-auto mb-3" style="width:56px;height:56px;font-size:24px;border-radius:14px;"><i class="bi bi-mortarboard-fill"></i></div>
      <?php endif; ?>
      <h1 class="h4 mb-1 fw-bold">Welcome back</h1>
      <p class="text-muted small mb-0">Sign in to <?= e($school['name'] ?? 'SSMS') ?></p>
    </div>

    <form method="post" action="<?= url('login') ?>" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="login_username">Username</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input class="form-control" id="login_username" name="username" value="<?= e($_POST['username'] ?? '') ?>" autocomplete="username" placeholder="Enter your username" autofocus required>
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label" for="login_password">Password</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input class="form-control" id="login_password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
          <button class="password-toggle" type="button" data-toggle-password="login_password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <div class="d-flex justify-content-end mb-4">
        <a class="small" href="<?= url('forgot-password') ?>">Forgot password?</a>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
    </form>

<?php if (User::withoutPassword()): ?>
    <div class="alert alert-warning small mt-4 mb-0 d-flex gap-2 app-alert" role="alert">
      <i class="bi bi-exclamation-triangle-fill app-alert-icon"></i>
      <div>An account still needs a password. <a href="<?= url('install') ?>" class="alert-link">Open first-time setup</a>.</div>
    </div>
<?php endif; ?>

    <p class="help-text justify-content-center mt-4 mb-0"><i class="bi bi-info-circle"></i><span>Use the account provided by your school administrator.</span></p>
  </div>
</div>
