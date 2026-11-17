<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($pageTitle ?? 'Dashboard') ?> &middot; <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="app-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="app-main">
    <header class="app-topbar">
      <button class="btn btn-light sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle navigation">&#9776;</button>
      <div class="topbar-context d-flex align-items-center gap-2">
        <?php $school = school_row(); if (!empty($school['logo_path'])): ?>
          <img src="<?= e(asset($school['logo_path'])) ?>" alt="" style="width:28px;height:28px;object-fit:contain;border-radius:6px;background:#fff;border:1px solid #e2e8f0;padding:2px;">
        <?php endif; ?>
        <div>
          <strong class="d-block lh-sm"><?= e($school['name'] ?? APP_NAME) ?></strong>
          <span class="topbar-term"><?= e(current_term_name()) ?></span>
        </div>
      </div>
      <div class="topbar-user">
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle me-1"></i><?= e(Auth::user()['full_name'] ?? '') ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header"><?= e(Auth::user()['role_name'] ?? '') ?></h6></li>
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

    <main class="app-content">
<?php foreach (flash_all() as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
<?php endforeach; ?>
<?= $content ?>
    </main>

    <footer class="app-footer">&copy; <?= date('Y') ?> <?= e(school_row()['name'] ?? APP_NAME) ?> &middot; <?= APP_NAME ?> <?= APP_TAGLINE ?></footer>
  </div>
</div>

<script>window.APP = <?= json_encode(['csrf' => Csrf::token(), 'base' => BASE_URL, 'urls' => ['store' => url('users/store'), 'update' => url('users/update')]], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
