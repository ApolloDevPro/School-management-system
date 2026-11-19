<?php
/**
 * Active sessions: every browser currently signed in to this school.
 *
 * The page reads the signed-in user's token hash to mark the current device,
 * and offers revoke buttons for administrators with security edit rights.
 */
$ago = function (?string $datetime): string {
    $ts = strtotime((string)$datetime);
    if (!$ts) {
        return '—';
    }
    $diff = max(0, time() - $ts);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return (int)floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return (int)floor($diff / 3600) . ' h ago';
    }
    return (int)floor($diff / 86400) . ' d ago';
};
$until = function (?string $datetime): string {
    $ts = strtotime((string)$datetime);
    if (!$ts) {
        return '—';
    }
    $diff = $ts - time();
    if ($diff <= 0) {
        return 'expired';
    }
    if ($diff < 3600) {
        return 'in ' . max(1, (int)floor($diff / 60)) . ' min';
    }
    return 'in ' . (int)floor($diff / 3600) . ' h ' . (int)floor(($diff % 3600) / 60) . ' m';
};
$browser = function (?string $ua): string {
    $ua = (string)$ua;
    $os = 'Unknown OS';
    foreach (['Windows NT' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Mac OS X' => 'macOS', 'Linux' => 'Linux'] as $needle => $label) {
        if (stripos($ua, $needle) !== false) {
            $os = $label;
            break;
        }
    }
    $name = 'Unknown browser';
    if (stripos($ua, 'Edg/') !== false) {
        $name = 'Edge';
    } elseif (stripos($ua, 'OPR/') !== false) {
        $name = 'Opera';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $name = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $name = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $name = 'Safari';
    }
    return $name . ' on ' . $os;
};
$deviceIcon = fn(string $d): string => match ($d) {
    'mobile' => 'bi-phone',
    'tablet' => 'bi-tablet',
    default  => 'bi-display',
};
$typeColor = fn(string $t): string => match ($t) {
    'admin'  => 'danger',
    'staff'  => 'primary',
    'parent' => 'info',
    default  => 'success',
};
$typeLabel = fn(string $t): string => match ($t) {
    'admin'  => 'Administrator',
    'staff'  => 'Staff',
    'parent' => 'Parent',
    default  => 'Student',
};

$rows       = $sessions ?? [];
$sessionCount = count($rows);
$userCount    = count(array_unique(array_column($rows, 'user_id')));
$portalCount  = count(array_filter($rows, fn(array $s): bool => in_array($s['portal_type'] ?? '', ['parent', 'student'], true)));
$mine         = (int)(Auth::user()['id'] ?? 0);
?>
<div class="page-head d-flex flex-wrap justify-content-between align-items-start gap-3">
  <div>
    <h1 class="h4 mb-0">Active sessions</h1>
    <p class="text-muted small mb-0">Every browser signed in to this school, with the option to end a session.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="<?= url('security/sessions') ?>"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
    <?php if (!empty($canRevoke) && empty($trackingError)): ?>
    <form method="post" action="<?= url('security/sessions/revoke-others') ?>" data-confirm="Sign out every other device signed in to your account?">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i> Sign out my other sessions</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($trackingError)): ?>
<div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
  <i class="bi bi-exclamation-triangle-fill mt-1"></i>
  <div><?= e($trackingError) ?></div>
</div>
<?php endif; ?>

<?php if (empty($trackingError)): ?>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100 tone-primary">
      <i class="stat-glyph bi bi-person-check" aria-hidden="true"></i>
      <div class="stat-label">Active sessions</div>
      <div class="stat-value"><?= number_format($sessionCount) ?></div>
      <div class="stat-note">Signed-in browsers</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100 tone-info">
      <i class="stat-glyph bi bi-people" aria-hidden="true"></i>
      <div class="stat-label">People online</div>
      <div class="stat-value"><?= number_format($userCount) ?></div>
      <div class="stat-note">Distinct accounts</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100 tone-success">
      <i class="stat-glyph bi bi-person-workspace" aria-hidden="true"></i>
      <div class="stat-label">Portal accounts</div>
      <div class="stat-value"><?= number_format($portalCount) ?></div>
      <div class="stat-note">Parents and students</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100 tone-secondary">
      <i class="stat-glyph bi bi-clock-history" aria-hidden="true"></i>
      <div class="stat-label">Session lifetime</div>
      <div class="stat-value">4 h</div>
      <div class="stat-note">Expires from sign-in</div>
    </div>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-person-check me-1"></i> Signed-in sessions <span class="badge text-bg-light"><?= number_format($sessionCount) ?></span></span>
    <span class="small text-muted d-none d-md-inline">Newest activity first</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>User</th>
          <th>Account</th>
          <th>Device</th>
          <th>IP address</th>
          <th>Signed in</th>
          <th>Last activity</th>
          <th>Expires</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
<?php if (!$rows): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-person-x d-block fs-3 mb-2"></i>
            No active sessions were found. Sign-ins are recorded as people use the system.
          </td>
        </tr>
<?php endif; ?>
<?php foreach ($rows as $s): ?>
<?php
  $isCurrent = !empty($currentHash) && hash_equals((string)$s['session_token_hash'], (string)$currentHash);
  $isMine    = (int)$s['user_id'] === $mine;
  $idle      = strtotime((string)$s['last_activity_at']) < time() - 1800;
?>
        <tr<?= $isCurrent ? ' class="table-primary"' : '' ?>>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="session-avatar"><?= e(mb_strtoupper(mb_substr((string)$s['full_name'], 0, 1))) ?></span>
              <div>
                <strong><?= e($s['full_name']) ?></strong>
                <?php if ($isCurrent): ?><span class="badge text-bg-primary ms-1">This device</span><?php endif; ?>
                <div class="small text-muted"><?= e($s['username']) ?><?= $s['role_name'] ? ' &middot; ' . e($s['role_name']) : '' ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge text-bg-<?= $typeColor((string)$s['portal_type']) ?>"><?= $typeLabel((string)$s['portal_type']) ?></span></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <i class="bi <?= $deviceIcon((string)$s['device_type']) ?> text-muted"></i>
              <div>
                <div class="small"><?= e($browser($s['user_agent'] ?? '')) ?></div>
                <div class="small text-muted text-truncate" style="max-width:220px" title="<?= e($s['user_agent'] ?? '') ?>"><?= e($s['user_agent'] ?? '—') ?></div>
              </div>
            </div>
          </td>
          <td class="small font-monospace"><?= e($s['ip_address'] ?? '—') ?></td>
          <td>
            <div class="small"><?= e(date('d M Y, H:i', strtotime((string)$s['started_at']))) ?></div>
            <div class="small text-muted"><?= $ago($s['started_at']) ?></div>
          </td>
          <td>
            <div class="small"><?= $ago($s['last_activity_at']) ?></div>
            <?php if ($idle): ?><span class="badge text-bg-warning">Idle</span><?php else: ?><span class="badge text-bg-success">Active</span><?php endif; ?>
          </td>
          <td class="small text-muted"><?= $until($s['expires_at']) ?></td>
          <td class="text-end text-nowrap">
            <?php if ($isCurrent): ?>
            <form method="post" action="<?= url('logout') ?>" class="d-inline">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-secondary" title="Sign out of this device"><i class="bi bi-box-arrow-right"></i></button>
            </form>
            <?php elseif (!empty($canRevoke)): ?>
            <form method="post" action="<?= url('security/sessions/revoke') ?>" class="d-inline" data-confirm="Sign out <?= e($s['full_name']) ?> on this device?">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" title="End this session"><i class="bi bi-x-octagon me-1"></i> Revoke</button>
            </form>
            <?php else: ?>
            <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card app-card">
  <div class="card-header"><i class="bi bi-info-circle me-1"></i> How sessions work</div>
  <div class="card-body small text-muted">
    <ul class="mb-0 ps-3">
      <li>Each browser sign-in creates a separate row, so the same person can appear more than once.</li>
      <li>Sessions end automatically 4 hours after sign-in; closing the browser tab alone does not end one.</li>
      <li>Revoking a session signs that device out on its next page request. The account's password is unchanged.</li>
      <li>Only accounts with the security <strong>view</strong> permission can see this page; ending sessions needs <strong>edit</strong>.</li>
    </ul>
  </div>
</div>
