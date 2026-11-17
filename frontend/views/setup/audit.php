<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Audit logs</h1>
    <p class="text-muted small mb-0">Who did what and when — the last 500 matching entries</p>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <form method="get" action="<?= url('setup/audit') ?>" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small" for="f_module">Module</label>
        <select class="form-select form-select-sm" id="f_module" name="module">
          <option value="">All modules</option>
<?php foreach ($modules as $module): ?>
          <option value="<?= e($module['module']) ?>" <?= $filters['module'] === $module['module'] ? 'selected' : '' ?>><?= e($module['module']) ?></option>
<?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small" for="f_user">User</label>
        <input class="form-control form-control-sm" id="f_user" name="user" value="<?= e($filters['user']) ?>" placeholder="Name contains…">
      </div>
      <div class="col-md-2">
        <label class="form-label small" for="f_from">From</label>
        <input class="form-control form-control-sm" id="f_from" name="from" type="date" value="<?= e($filters['from']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small" for="f_to">To</label>
        <input class="form-control form-control-sm" id="f_to" name="to" type="date" value="<?= e($filters['to']) ?>">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-sm btn-primary" type="submit">Filter</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('setup/audit') ?>">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card app-card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" data-table>
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Role</th>
            <th>Module</th>
            <th>Action</th>
            <th>Summary</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
<?php if (!$logs): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No audit entries match the filter.</td></tr>
<?php endif; ?>
<?php foreach ($logs as $log): ?>
          <tr>
            <td class="text-nowrap small"><?= e(date('d M Y, H:i', strtotime($log['created_at']))) ?></td>
            <td><?= e($log['user_name'] ?? 'System') ?></td>
            <td class="small"><?= e($log['role_name'] ?? '—') ?></td>
            <td><span class="badge text-bg-light"><?= e($log['module'] ?? '—') ?></span></td>
            <td class="small"><?= e($log['action']) ?></td>
            <td>
              <?= e($log['summary'] ?? '') ?>
<?php if ($log['old_values'] || $log['new_values']): ?>
              <details class="small text-muted">
                <summary>Details</summary>
                <pre class="small mb-0 mt-1" style="max-width:420px;white-space:pre-wrap"><?= e(json_encode(['old' => $log['old_values'] ? json_decode($log['old_values'], true) : null, 'new' => $log['new_values'] ? json_decode($log['new_values'], true) : null], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
              </details>
<?php endif; ?>
            </td>
            <td class="small text-muted"><?= e($log['ip_address'] ?? '') ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
