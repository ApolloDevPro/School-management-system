<div class="auth-card card border-0 shadow-sm">
  <div class="card-body p-4 p-md-5">
    <div class="mb-4">
      <h1 class="h4 mb-1 fw-semibold">Forgot password</h1>
      <p class="text-muted small mb-0">Enter your username or email to receive a reset link.</p>
    </div>
    <form method="post" action="<?= url('forgot-password') ?>">
      <?= csrf_field() ?>
      <div class="mb-4">
        <label class="form-label" for="fp_identity">Username or email</label>
        <div class="input-group input-group-lg">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input class="form-control" id="fp_identity" name="identity" autocomplete="username" placeholder="Enter your username or email" required>
        </div>
        <div class="help-text"><i class="bi bi-info-circle"></i><span>Ask your school administrator if you do not know the username on your account.</span></div>
      </div>
      <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-send me-1"></i> Send reset link</button>
    </form>
    <p class="small text-muted mt-4 mb-0 text-center"><a href="<?= url('login') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
  </div>
</div>