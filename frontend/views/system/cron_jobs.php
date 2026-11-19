<div class="page-head">
  <div>
    <h1 class="h4 mb-0">Scheduled Tasks</h1>
    <p class="text-muted small mb-0">Configure and monitor background jobs such as fee reminders, backups, and queues</p>
  </div>
  <div class="text-end">
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cronModal"><i class="bi bi-plus"></i> Add task</button>
  </div>
</div>

<div class="card app-card mb-3">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Task name</th>
            <th>Description</th>
            <th>Handler</th>
            <th>Interval</th>
            <th>Next run</th>
            <th>Last run</th>
            <th>Status</th>
            <th>Runs</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
<?php if (!$jobs): ?><tr><td colspan="9" class="text-center text-muted py-4">No scheduled tasks found.</td></tr><?php endif; ?>
<?php foreach ($jobs as $job): ?>
          <tr>
            <td><strong><?= e($job['name']) ?></strong></td>
            <td><?= e($job['description'] ?? '—') ?></td>
            <td class="small text-muted"><?= e($job['handler_class']) ?>::<?= e($job['handler_method']) ?></td>
            <td><?= (int)$job['interval_minutes'] ?> min</td>
            <td><?= e($job['next_run_at'] ?? '—') ?></td>
            <td><?= e($job['last_run_at'] ?? '—') ?></td>
            <td><span class="badge bg-<?= $job['last_run_status'] === 'completed' ? 'success' : ($job['last_run_status'] === 'failed' ? 'danger' : ($job['last_run_status'] === 'running' ? 'warning' : 'secondary')) ?>"><?= e(ucfirst($job['last_run_status'] ?? 'pending')) ?></span></td>
            <td><?= (int)$job['run_count'] ?></td>
            <td class="text-end text-nowrap">
              <form method="post" action="<?= url('system/cron-jobs/toggle') ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                <input type="hidden" name="is_active" value="<?= (int)$job['is_active'] === 1 ? 0 : 1 ?>">
                <button class="btn btn-sm btn-<?= $job['is_active'] === 1 ? 'outline-danger' : 'outline-success' ?>">
                  <?= $job['is_active'] === 1 ? 'Pause' : 'Activate' ?>
                </button>
              </form>
              <form method="post" action="<?= url('system/cron-jobs/run') ?>" class="d-inline" onsubmit="return confirm('Run this task now?')">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-play"></i> Run now</button>
              </form>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($logs): ?>
<div class="card app-card mb-3">
  <div class="card-body">
    <h6 class="card-title">Recent execution log</h6>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr><th>Time</th><th>Task</th><th>Status</th><th>Message</th><th>Duration</th></tr>
        </thead>
        <tbody>
<?php foreach ($logs as $log): ?>
          <tr>
            <td><?= e($log['created_at']) ?></td>
            <td><?= e($log['job_name']) ?></td>
            <td><span class="badge bg-<?= $log['status'] === 'success' ? 'success' : 'danger' ?>"><?= e(ucfirst($log['status'])) ?></span></td>
            <td><?= e($log['message'] ?? '—') ?></td>
            <td><?= (int)$log['duration_ms'] ?> ms</td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="cronModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= url('system/cron-jobs/save') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h5 class="modal-title">Add scheduled task</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" value="0">
          <div class="mb-3">
            <label class="form-label">Task name</label>
            <input class="form-control" name="name" required maxlength="140" placeholder="e.g. Fee reminder">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input class="form-control" name="description" maxlength="255" placeholder="e.g. Send daily fee reminders to parents">
          </div>
          <div class="mb-3">
            <label class="form-label">Handler class</label>
            <input class="form-control" name="handler_class" required maxlength="160" placeholder="e.g. FeeReminderJob">
            <div class="form-text">The PHP class that implements the task logic.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Handler method</label>
            <input class="form-control" name="handler_method" value="run" maxlength="60" placeholder="run">
          </div>
          <div class="mb-3">
            <label class="form-label">Interval (minutes)</label>
            <input class="form-control" type="number" name="interval_minutes" value="60" min="1" max="525600">
          </div>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
            <label class="form-check-label" for="is_active">Active</label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">Save task</button>
        </div>
      </form>
    </div>
  </div>
</div>
