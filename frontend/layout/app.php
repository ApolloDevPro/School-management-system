<!doctype html>
<html lang="en">
<head>
<?php /* Shared head: bundled Bootstrap, icons, DataTables and the app stylesheet. */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($pageTitle ?? 'Dashboard') ?> &middot; <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/forms.css') ?>">
</head>
<body>
<?php /* Keyboard users can jump past the sidebar without tabbing through it. */ ?>
<a class="skip-link" href="#mainContent">Skip to main content</a>
<div class="app-shell">
<?php /* Sidebar is permission-filtered in sidebar.php. */ ?>
<?php require __DIR__ . '/sidebar.php'; ?>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="app-main">
    <?php
    /* Sticky top bar: school identity, active term, live date/time and account menu. */
    $topbarUser = Auth::user();
    $topbarName = trim((string)($topbarUser['full_name'] ?? ''));
    $topbarRole = trim((string)($topbarUser['role_name'] ?? ''));
    $topbarParts = preg_split('/\s+/', $topbarName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $topbarInitials = '';
    if ($topbarParts !== []) {
        $topbarInitials = mb_substr($topbarParts[0], 0, 1);
        if (count($topbarParts) > 1) {
            $topbarInitials .= mb_substr($topbarParts[count($topbarParts) - 1], 0, 1);
        }
        $topbarInitials = mb_strtoupper($topbarInitials);
    }
    ?>
    <header class="app-topbar">
      <button class="btn btn-light sidebar-toggle" id="sidebarToggle" type="button" aria-label="Open navigation menu">&#9776;</button>
      <div class="topbar-brand">
        <?php $school = school_row(); if (!empty($school['logo_path'])): ?>
          <img class="topbar-logo" src="<?= e(asset($school['logo_path'])) ?>" alt="School logo">
        <?php endif; ?>
        <div class="topbar-context">
          <div class="topbar-identity">
            <strong class="topbar-school"><?= e($school['name'] ?? APP_NAME) ?></strong>
            <span class="topbar-term"><i class="bi bi-calendar3-week" aria-hidden="true"></i><span><?= e(current_term_name()) ?></span></span>
          </div>
          <?php require __DIR__ . '/_topbar_datetime.php'; ?>
        </div>
      </div>
      <div class="topbar-user">
        <div class="dropdown">
          <button class="topbar-account dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account menu" aria-label="Account menu for <?= e($topbarName) ?>">
            <span class="topbar-avatar" aria-hidden="true"><?= e($topbarInitials ?: '?') ?></span>
            <span class="topbar-account-text">
              <span class="topbar-account-name"><?= e($topbarName) ?></span>
              <span class="topbar-account-role"><?= e($topbarRole) ?></span>
            </span>
            <i class="bi bi-chevron-down topbar-caret" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><?= e($topbarRole ?: 'Account') ?></h6></li>
            <li><a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>My account</a></li>
            <li><a class="dropdown-item" href="<?= url('change-password') ?>"><i class="bi bi-key me-2"></i>Change password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="post" action="<?= url('logout') ?>" class="px-2 py-1 m-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger w-100" type="submit">Sign out</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="app-content" id="mainContent">
<?php /* Flash messages are queued by controllers; successes dismiss themselves after a few seconds. */ ?>
<?php foreach (flash_all() as $flash):
    $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
    $icon = ['success' => 'check-circle-fill', 'danger' => 'x-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$type];
?>
      <div class="alert alert-<?= e($type) ?> alert-dismissible fade show app-alert" role="alert"<?= $type === 'success' ? ' data-autodismiss="8000"' : '' ?>>
        <i class="bi bi-<?= e($icon) ?> app-alert-icon" aria-hidden="true"></i>
        <div><?= e($flash['message']) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
      </div>
<?php endforeach; ?>
    <?php /* Each module view renders its own page header and cards inside $content. */ ?>
<?= $content ?>
    </main>

    <footer class="app-footer">
      <span>&copy; <?= date('Y') ?> <?= e(school_row()['name'] ?? APP_NAME) ?> &middot; <?= APP_NAME ?> <?= APP_TAGLINE ?></span>
      <span class="d-none d-md-inline">Need help? Contact your system administrator.</span>
    </footer>
  </div>
</div>

<div class="toast-container" id="toastWrap" aria-live="polite" aria-atomic="true"></div>
<button class="scroll-top" id="scrollTop" type="button" aria-label="Back to top" title="Back to top"><i class="bi bi-arrow-up"></i></button>

<script>window.APP = <?= json_encode(['csrf' => Csrf::token(), 'base' => BASE_URL, 'urls' => ['store' => url('users/store'), 'update' => url('users/update')]], JSON_UNESCAPED_SLASHES) ?>;</script>
<?php /* Vendor scripts first, then js/app.js for modals, AJAX forms, tables and niceties. */ ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
