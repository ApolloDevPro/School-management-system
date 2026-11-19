<?php
$priorityColor = fn(string $p): string => match($p) { 'urgent' => 'danger', 'high' => 'warning', 'low' => 'secondary', default => 'primary' };
$statusColor   = fn(string $s): string => match($s) { 'resolved' => 'success', 'closed' => 'secondary', 'in_progress' => 'info', default => 'primary' };
$has2fa        = false; // placeholder; controller may inject

$user = Auth::user() ?? [];
$userId   = (int)($user['id'] ?? 0);
$schoolId = school_id();
$isAdmin  = Auth::can('administration', 'view');
?>
<div class="page-head d-flex justify-content-between align-items-start">
  <div>
    <h1 class="h4 mb-0">Support &amp; security</h1>
    <p class="text-muted small mb-0">Tickets, backups, database views, and account security.</p>
  </div>
</div>

<!-- ── Account security ─────────────────────────────────────────── -->
<div class="card app-card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-shield-lock me-1"></i> Account security</span>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="<?= url('security/sessions') ?>"><i class="bi bi-person-check me-1"></i> Active sessions</a>
      <a class="btn btn-sm btn-outline-primary" href="<?= url('security/2fa-setup') ?>"><i class="bi bi-phone me-1"></i> 2FA setup</a>
    </div>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3">Protect your account with two-factor authentication and keep your password up to date.</p>
    <div class="row g-3">
      <div class="col-md-6 col-lg-4">
        <div class="action-card h-100">
          <div class="action-card-icon primary"><i class="bi bi-phone"></i></div>
          <h6>Two-factor authentication</h6>
          <p class="mb-0">Add a second verification step using an authenticator app (Google Authenticator, Authy, etc.).</p>
          <a href="<?= url('security/2fa-setup') ?>" class="btn btn-sm btn-outline-primary mt-3">Set up 2FA</a>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="action-card h-100">
          <div class="action-card-icon success"><i class="bi bi-key"></i></div>
          <h6>Change password</h6>
          <p class="mb-0">Keep your account password current. Users flagged to change password are redirected until updated.</p>
          <a href="<?= url('change-password') ?>" class="btn btn-sm btn-outline-primary mt-3">Change password</a>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="action-card h-100">
          <div class="action-card-icon info"><i class="bi bi-person-check"></i></div>
          <h6>Signed in as</h6>
          <p class="mb-0 small text-muted"><?= e($user['full_name'] ?? '—') ?> <br><?= e($user['role_name'] ?? '—') ?> &middot; <?= e($user['username'] ?? '—') ?></p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Data & maintenance ───────────────────────────────────────── -->
<div class="card app-card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-database-gear me-1"></i> Data &amp; maintenance</span>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('security/backups') ?>"><i class="bi bi-archive me-1"></i> Backups</a>
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3">Install reporting views and manage database backups (requires server tools like <code>mysqldump</code> for backups).</p>
    <div class="row g-3">
      <div class="col-md-6 col-lg-4">
        <div class="action-card h-100">
          <div class="action-card-icon primary"><i class="bi bi-database-check"></i></div>
          <h6>Install DB views</h6>
          <p class="mb-0">Create all required SQL views for reports and analytics from <code>database/19_reports_views.sql</code>.</p>
          <a href="<?= url('security/db-setup') ?>" class="btn btn-sm btn-outline-primary mt-3">Install views</a>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="action-card h-100">
          <div class="action-card-icon secondary"><i class="bi bi-save"></i></div>
          <h6>Manage backups</h6>
          <p class="mb-0">Create, download, and delete full SQL database backups for disaster recovery.</p>
          <a href="<?= url('security/backups') ?>" class="btn btn-sm btn-outline-primary mt-3">View backups</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Notes ────────────────────────────────────────────────────── -->
<div class="card app-card mb-4">
  <div class="card-header"><i class="bi bi-info-circle me-1"></i> Notes</div>
  <div class="card-body small text-muted">
    <ul class="mb-0 ps-3">
      <li>Reports/analytics require database views from <code>database/19_reports_views.sql</code> (use Install DB views).</li>
      <li>Login 2FA is enforced automatically for accounts with authenticator 2FA enabled.</li>
      <li>Users flagged to change password are redirected until updated.</li>
    </ul>
  </div>
</div>

<!-- ── Support tickets ──────────────────────────────────────────── -->
<div class="row g-3">
  <div class="col-xl-4">
    <div class="card app-card h-100">
      <div class="card-header">Open a ticket</div>
      <div class="card-body">
        <form method="post" action="<?= url('security/tickets/create') ?>" data-ajax>
          <?= csrf_field() ?>
          <div class="form-section"><i class="bi bi-life-preserver"></i> Ticket details</div>
          <div class="mb-2">
            <label class="form-label">Subject <span class="text-danger">*</span></label>
            <input class="form-control" name="subject" maxlength="255" required>
            <span class="invalid-feedback d-block" data-error-for="subject"></span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col">
              <label class="form-label">Category</label>
              <select class="form-select" name="category">
                <option value="general">General</option>
                <option value="technical">Technical</option>
                <option value="access">Access / login</option>
                <option value="data">Data issue</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col">
              <label class="form-label">Priority</label>
              <select class="form-select" name="priority">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Description <span class="text-danger">*</span></label>
            <textarea class="form-control" name="description" rows="4" required></textarea>
            <span class="invalid-feedback d-block" data-error-for="description"></span>
          </div>
          <button class="btn btn-primary w-auto" type="submit">Submit ticket</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card app-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= $isAdmin ? 'All tickets' : 'My tickets' ?> <span class="badge text-bg-light"><?= count($tickets ?? []) ?></span></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-table>
          <thead><tr><th>Ticket #</th><th>Subject</th><th>Category</th><th>Priority</th><th>Opened</th><th>Status</th><th style="width:14%">Action</th></tr></thead>
          <tbody>
            <?php if (!($tickets ?? [])): ?><tr><td colspan="7" class="text-center text-muted py-4">No tickets found.</td></tr><?php endif; ?>
            <?php foreach ($tickets ?? [] as $t): ?>
            <tr>
              <td><strong><?= e($t['ticket_number'] ?? '—') ?></strong></td>
              <td>
                <?= e($t['subject'] ?? '—') ?>
                <?php if ($isAdmin && isset($t['requester'])): ?>
                <div class="small text-muted"><?= e($t['requester']) ?></div>
                <?php endif; ?>
              </td>
              <td><?= e($t['category'] ?? '—') ?></td>
              <td><span class="badge text-bg-<?= $priorityColor($t['priority'] ?? 'normal') ?>"><?= e($t['priority'] ?? 'normal') ?></span></td>
              <td class="small"><?= e(date('d M Y', strtotime($t['created_at'] ?? ''))) ?></td>
              <td><span class="badge text-bg-<?= $statusColor($t['status'] ?? 'open') ?>"><?= e(str_replace('_', ' ', $t['status'] ?? 'open')) ?></span></td>
              <td>
                <?php if (!in_array($t['status'] ?? '', ['resolved','closed'], true)): ?>
                <form method="post" action="<?= url('security/tickets/update') ?>" data-ajax>
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)($t['id'] ?? 0) ?>">
                  <select class="form-select form-select-sm" name="status" onchange="this.form.requestSubmit()" style="width:auto">
                    <option value="open"        <?= ($t['status'] ?? '') === 'open'        ? 'selected' : '' ?>>Open</option>
                    <?php if ($isAdmin): ?>
                    <option value="in_progress" <?= ($t['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                    <option value="resolved"    <?= ($t['status'] ?? '') === 'resolved'    ? 'selected' : '' ?>>Resolved</option>
                    <?php endif; ?>
                    <option value="closed"      <?= ($t['status'] ?? '') === 'closed'      ? 'selected' : '' ?>>Closed</option>
                  </select>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
