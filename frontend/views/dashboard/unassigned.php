<?php
$user = $user ?? Auth::user();
?>
<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Dashboard</h1>
    <p class="text-muted small mb-0">Welcome, <?= e($user['full_name'] ?? '') ?></p>
  </div>
</div>

<div class="card app-card">
  <div class="card-body text-center py-5">
    <i class="bi bi-person-badge text-muted" style="font-size:2.5rem"></i>
    <h2 class="h5 mt-3">No dashboard is assigned to this role</h2>
    <p class="text-muted mb-1">
      Your account uses the role <strong><?= e($user['role_name'] ?? 'No role assigned') ?></strong>
      (<?= e($user['user_type'] ?? 'staff') ?>).
    </p>
    <p class="text-muted small mb-4">
      The standard dashboards are assigned by role. If you need access, ask an administrator to give
      your account one of the standard roles. You will not see school-wide figures until then.
    </p>
    <div class="d-flex justify-content-center gap-2">
      <a class="btn btn-outline-primary" href="<?= url('profile') ?>">My profile</a>
      <form method="post" action="<?= url('logout') ?>" class="m-0">
        <?= csrf_field() ?>
        <button class="btn btn-outline-secondary" type="submit">Sign out</button>
      </form>
    </div>
  </div>
</div>
