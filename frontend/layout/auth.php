<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($pageTitle ?? 'Sign in') ?> &middot; <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-brand">
      <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
      <span class="brand-name"><?= e(APP_NAME) ?></span>
      <span class="brand-tag"><?= e(APP_TAGLINE) ?></span>
    </div>

<?php foreach (flash_all() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
      <?= e($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>

<?= $content ?>
    <p class="auth-foot text-center small mt-3 mb-0">&copy; <?= date('Y') ?> <?= e(school_row()['name'] ?? APP_NAME) ?></p>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
