<!doctype html>
<html lang="en">
<head>
<?php /* Minimal shell for signed-out screens: Bootstrap, icons and the app stylesheet only. */ ?>
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

<?php /* Flash messages (e.g. "reset link sent") are shown above the form. */ ?>
<?php foreach (flash_all() as $flash):
    $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
    $icon = ['success' => 'check-circle-fill', 'danger' => 'x-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$type];
?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show shadow-sm app-alert" role="alert"<?= $type === 'success' ? ' data-autodismiss="8000"' : '' ?>>
      <i class="bi bi-<?= e($icon) ?> app-alert-icon" aria-hidden="true"></i>
      <div><?= e($flash['message']) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
    </div>
<?php endforeach; ?>

<?php /* The screen itself (login, install, reset, two-factor) is supplied by the controller. */ ?>
<?= $content ?>
    <p class="auth-foot text-center small mt-3 mb-0">&copy; <?= date('Y') ?> <?= e(school_row()['name'] ?? APP_NAME) ?></p>
  </div>

<div class="toast-container" id="toastWrap" aria-live="polite" aria-atomic="true"></div>
<script>window.APP = <?= json_encode(['csrf' => Csrf::token(), 'base' => BASE_URL], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
