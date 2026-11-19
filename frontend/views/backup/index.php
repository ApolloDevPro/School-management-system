<?php
$canCreate = $canCreate ?? false;
$canDelete = $canDelete ?? false;

$total   = count($backups ?? []);
$success = array_reduce($backups ?? [], fn ($c, $b) => $c + ((($b['status'] ?? '') === 'success') ? 1 : 0), 0);
$failed  = array_reduce($backups ?? [], fn ($c, $b) => $c + ((($b['status'] ?? '') === 'failed') ? 1 : 0), 0);
$last    = $backups ? e(date('d M Y', strtotime($backups[0]['started_at'] ?? ''))) : '—';
?>
<div class="page-head d-flex justify-content-between align-items-start">
  <div>
    <h1 class="h4 mb-0">Data Backups</h1>
    <p class="text-muted small mb-0">Scheduled and manual database backups for disaster recovery.</p>
  </div>
  <?php if ($canCreate): ?>
  <button class="btn btn-primary" type="button" data-toggle-modal="createBackupModal" data-empty><i class="bi bi-plus me-1"></i> Create Backup</button>
  <?php endif; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-primary stat-with-icon">
      <div class="stat-icon"><i class="bi bi-database"></i></div>
      <div class="stat-body">
        <div class="stat-label">Total Backups</div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-note">All backup jobs</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-success stat-with-icon">
      <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Successful</div>
        <div class="stat-value"><?= $success ?></div>
        <div class="stat-note">Completed successfully</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-danger stat-with-icon">
      <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
      <div class="stat-body">
        <div class="stat-label">Failed</div>
        <div class="stat-value"><?= $failed ?></div>
        <div class="stat-note">Require attention</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card tone-info stat-with-icon">
      <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
      <div class="stat-body">
        <div class="stat-label">Last Backup</div>
        <div class="stat-value"><?= $last ?></div>
        <div class="stat-note">Most recent job</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card app-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Backup History</span>
      </div>
      <div class="card-body p-0">
<?php if (!($backups ?? [])): ?>
        <div class="text-muted text-center p-4">No backups have been created yet.</div>
<?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" data-table>
            <thead>
              <tr>
                <th>File</th><th>Type</th><th>Size</th><th>Started</th><th>Status</th><th>Trigger</th><th style="width:16%">Actions</th>
              </tr>
            </thead>
            <tbody>
<?php foreach ($backups as $b): ?>
              <tr>
                <td><?= e(basename($b['file_path'] ?? '—')) ?></td>
                <td><?= e(ucfirst($b['backup_type'] ?? 'database')) ?></td>
                <td><?= $b['file_size_bytes'] ? number_format($b['file_size_bytes'] / 1024, 1) . ' KB' : '—' ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($b['started_at']))) ?></td>
                <td><span class="badge text-bg-<?= $b['status'] === 'success' ? 'success' : ($b['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= e(ucfirst($b['status'] ?? 'unknown')) ?></span></td>
                <td><?= e(ucfirst($b['trigger_type'] ?? 'manual')) ?></td>
                <td class="text-nowrap">
<?php if ($b['status'] === 'success'): ?>
                  <a class="btn btn-outline-primary btn-sm" href="<?= url('security/backups/download', ['id' => (int)$b['id']]) ?>"><i class="bi bi-download"></i></a>
<?php if ($canRestore): ?>
                  <button class="btn btn-outline-warning btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#restoreBackupModal"
                          data-backup-id="<?= (int)$b['id'] ?>" data-backup-name="<?= e(basename($b['file_path'] ?? '')) ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
<?php endif; ?>
<?php endif; ?>
<?php if ($canDelete): ?>
                  <form method="post" action="<?= url('security/backups/delete') ?>" class="d-inline" data-confirm="Delete this backup file?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
<?php endif; ?>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($canRestore): ?>
<div class="card app-card mt-3 border-warning">
  <div class="card-header text-warning-emphasis"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore from an SQL file</div>
  <div class="card-body">
    <p class="text-muted small mb-3">Restoring replaces current records with the contents of the file. Create a fresh backup before restoring. Type RESTORE to confirm.</p>
    <form method="post" action="<?= url('security/backups/restore') ?>" enctype="multipart/form-data" class="row g-2 align-items-end" onsubmit="return confirm('Replace the live database with this file?');">
      <?= csrf_field() ?>
      <div class="col-md-5"><label class="form-label" for="sql_file">SQL file (.sql)</label><input class="form-control" id="sql_file" type="file" name="sql_file" accept=".sql" required></div>
      <div class="col-md-3"><label class="form-label" for="restore_confirm">Type RESTORE</label><input class="form-control" id="restore_confirm" name="confirm" autocomplete="off" required></div>
      <div class="col-md-2"><button class="btn btn-warning w-100" type="submit">Restore</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="modal fade" id="restoreBackupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Restore database</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form method="post" action="<?= url('security/backups/restore') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="restore_backup_id" value="0">
        <div class="modal-body">
          <p class="mb-2">Restoring <strong id="restore_backup_name">this backup</strong> replaces all current records.</p>
          <label class="form-label" for="restore_backup_confirm">Type RESTORE to confirm</label>
          <input class="form-control" id="restore_backup_confirm" name="confirm" autocomplete="off" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-warning" type="submit">Restore database</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('restoreBackupModal')?.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;
  document.getElementById('restore_backup_id').value = button.getAttribute('data-backup-id') || '0';
  document.getElementById('restore_backup_name').textContent = button.getAttribute('data-backup-name') || 'this backup';
  document.getElementById('restore_backup_confirm').value = '';
});
</script>

<div class="modal fade" id="createBackupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Database Backup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="<?= url('security/backups/create') ?>" data-ajax>
        <?= csrf_field() ?>
        <div class="modal-body">
          <p class="text-muted small">This will create a full SQL dump of the school database using mysqldump. The backup file will be stored on the server and can be downloaded.</p>
          <div class="alert alert-info border-0 bg-info bg-opacity-10">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Note:</strong> Requires mysqldump to be available on the server. For automated scheduled backups, configure a server cron task.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Backup</button>
        </div>
      </form>
    </div>
  </div>
</div>
