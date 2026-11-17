<div class="auth-card card border-0 shadow-lg">
  <div class="card-body p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="brand-mark mx-auto mb-3" style="width:56px;height:56px;font-size:22px;border-radius:14px;">S</div>
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
      <div class="mb-4">
        <label class="form-label" for="login_password">Password</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input class="form-control" id="login_password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
        </div>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="remember_hint" disabled>
          <label class="form-check-label small text-muted" for="remember_hint">Keep me signed in</label>
        </div>
        <a class="small" href="<?= url('forgot-password') ?>">Forgot password?</a>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
    </form>

<?php if (User::withoutPassword()): ?>
    <div class="alert alert-warning small mt-4 mb-0 d-flex gap-2" role="alert">
      <i class="bi bi-exclamation-triangle"></i>
      <div>An account still needs a password. <a href="<?= url('install') ?>" class="alert-link">Open first-time setup</a>.</div>
    </div>
<?php endif; ?>
  </div>
</div>
