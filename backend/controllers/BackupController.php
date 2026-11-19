<?php
declare(strict_types=1);

/**
 * Database backup creation, download and deletion using mysqldump.
 *
 * Backend controller: receives routed requests, enforces permissions and
 * renders a view or returns JSON through the base Controller helpers.
 */
final class BackupController extends Controller
{
    private const BACKUP_DIR = __DIR__ . '/../../backups';

    public function index(): void
    {
        if (!$this->allowed('security', 'view')) {
            return;
        }
        $schoolId = school_id();

        $backups = Database::query(
            'SELECT b.*, u.full_name AS performed_by_name
               FROM data_backups b
               LEFT JOIN users u ON u.id = b.performed_by
              WHERE b.school_id = ? OR b.school_id IS NULL
              ORDER BY b.started_at DESC',
            [$schoolId]
        )->fetchAll();

        $this->render('backup/index', [
            'pageTitle' => 'Data Backups',
            'activeNav' => 'security',
            'backups' => $backups,
            'canCreate' => Auth::can('security', 'add'),
            'canDelete' => Auth::can('security', 'delete'),
            'canRestore' => Auth::can('security', 'approve'),
        ]);
    }

    public function create(): void
    {
        if (!$this->allowed('security', 'add')) {
            return;
        }
        $schoolId = school_id();
        $actorId = (int)Auth::user()['id'];

        if (!is_dir(self::BACKUP_DIR)) {
            @mkdir(self::BACKUP_DIR, 0755, true);
        }

        $filename = 'ssms_backup_' . date('Ymd_His') . '.sql';
        $filePath = self::BACKUP_DIR . '/' . $filename;

        // Keep credentials out of the command line by using a temporary option file.
        $cnf = tempnam(sys_get_temp_dir(), 'ssmsdump');
        if ($cnf === false) {
            $this->respond(false, 'Could not create a temporary MySQL option file.', 'security');
        }
        $quote = static fn (string $value): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        file_put_contents(
            $cnf,
            "[client]\nhost={$quote(DB_HOST)}\nuser={$quote(DB_USER)}\npassword={$quote(DB_PASS)}\n"
        );
        @chmod($cnf, 0600);

        $cmd = sprintf(
            'mysqldump --defaults-extra-file=%s %s --single-transaction --routines --triggers --result-file=%s 2>&1',
            escapeshellarg($cnf),
            escapeshellarg(DB_NAME),
            escapeshellarg($filePath)
        );

        $started = date('Y-m-d H:i:s');
        $outputCapture = [];
        $exitCode = null;
        @exec($cmd, $outputCapture, $exitCode);
        @unlink($cnf);
        $output = implode("\n", $outputCapture);
        $finished = date('Y-m-d H:i:s');

        $status = 'failed';
        $errorMessage = null;
        $fileSize = null;
        $checksum = null;

        if ($exitCode === 0 && is_file($filePath)) {
            $status = 'success';
            $fileSize = filesize($filePath);
            $checksum = hash_file('sha256', $filePath);
        } else {
            $errorMessage = trim($output) ?: ('Backup command failed (exit code ' . (int)$exitCode . '). Ensure mysqldump is installed and accessible to the web server.');
        }

        Database::conn()->prepare(
            'INSERT INTO data_backups
                (school_id, backup_type, file_path, file_size_bytes, checksum, started_at, finished_at,
                 duration_seconds, status, error_message, performed_by, trigger_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $schoolId, 'database', $filePath, $fileSize, $checksum, $started, $finished,
            $exitCode === 0 ? (int)(strtotime($finished) - strtotime($started)) : null,
            $status, $errorMessage, $actorId, 'manual',
        ]);

        audit('security', 'backup', 'data_backups', null, 'Created database backup ' . $filename);

        if ($status === 'success') {
            $this->respond(true, 'Backup created successfully.', 'security');
        } else {
            $this->respond(false, 'Backup failed: ' . $errorMessage, 'security');
        }
    }

    public function download(): void
    {
        if (!$this->allowed('security', 'view')) {
            return;
        }
        $id = (int)($_GET['id'] ?? 0);
        $schoolId = school_id();

        $row = Database::query(
            'SELECT * FROM data_backups WHERE id = ? AND (school_id = ? OR school_id IS NULL) AND status = \'success\'',
            [$id, $schoolId]
        )->fetch();

        if (!$row || !is_file($row['file_path'])) {
            $this->respond(false, 'Backup file not found.', 'security');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($row['file_path']) . '"');
        header('Content-Length: ' . $row['file_size_bytes']);
        header('Cache-Control: no-cache');
        readfile($row['file_path']);
        exit;
    }

    /**
     * Import a stored backup or an uploaded SQL file into the live database.
     *
     * This replaces current data, so the operator must type RESTORE and must
     * hold the approve permission.
     */
    public function restore(): void
    {
        if (!$this->allowed('security', 'approve')) {
            return;
        }
        if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'RESTORE') {
            $this->respond(false, 'Type RESTORE to confirm replacing the live database.', 'security/backups');
        }

        $schoolId = school_id();
        $path = null;
        $label = '';
        $backupId = (int)($_POST['id'] ?? 0);
        if ($backupId > 0) {
            $row = Database::query(
                'SELECT * FROM data_backups WHERE id = ? AND (school_id = ? OR school_id IS NULL) AND status = \'success\'',
                [$backupId, $schoolId]
            )->fetch();
            if (!$row || !is_file($row['file_path'])) {
                $this->respond(false, 'Backup file not found.', 'security/backups');
            }
            if (!empty($row['checksum']) && hash_file('sha256', $row['file_path']) !== $row['checksum']) {
                $this->respond(false, 'The backup checksum does not match. Import cancelled.', 'security/backups');
            }
            $path = $row['file_path'];
            $label = basename($path);
        } else {
            $file = $_FILES['sql_file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $this->respond(false, 'Choose a stored backup or upload a .sql file.', 'security/backups');
            }
            if ((int)$file['size'] > 256 * 1024 * 1024) {
                $this->respond(false, 'The SQL file is larger than the 256 MB limit.', 'security/backups');
            }
            if (strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'sql') {
                $this->respond(false, 'Only .sql files can be restored.', 'security/backups');
            }
            if (!is_dir(self::BACKUP_DIR)) {
                @mkdir(self::BACKUP_DIR, 0755, true);
            }
            $upload = self::BACKUP_DIR . '/restore_upload_' . date('Ymd_His') . '.sql';
            if (!move_uploaded_file($file['tmp_name'], $upload)) {
                $this->respond(false, 'The uploaded file could not be stored.', 'security/backups');
            }
            $path = $upload;
            $label = (string)$file['name'];
        }

        $cnf = tempnam(sys_get_temp_dir(), 'ssmsrestore');
        if ($cnf === false) {
            $this->respond(false, 'Could not create a temporary MySQL option file.', 'security/backups');
        }
        $quote = static fn (string $value): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        file_put_contents(
            $cnf,
            "[client]\nhost={$quote(DB_HOST)}\nuser={$quote(DB_USER)}\npassword={$quote(DB_PASS)}\n"
        );
        @chmod($cnf, 0600);

        // The shell feeds the dump into the mysql client, which recreates the
        // tables and rows exactly as they were in the backup.
        $cmd = sprintf(
            'mysql --defaults-extra-file=%s --default-character-set=utf8mb4 %s < %s 2>&1',
            escapeshellarg($cnf),
            escapeshellarg(DB_NAME),
            escapeshellarg($path)
        );
        $output = [];
        $exitCode = null;
        @exec($cmd, $output, $exitCode);
        @unlink($cnf);
        if ($backupId === 0 && is_file($path)) {
            @unlink($path);
        }

        if ($exitCode !== 0) {
            $message = trim(implode("\n", $output));
            $this->respond(false, 'Restore failed: ' . ($message !== '' ? $message : 'mysql returned an error.'), 'security/backups');
        }

        audit('security', 'restore', 'data_backups', $backupId ?: null, 'Restored database from ' . $label);
        $this->respond(true, 'Database restored from ' . $label . '. Sign in again if your session was stored in the backup.', 'security/backups');
    }

    public function delete(): void
    {
        if (!$this->allowed('security', 'delete')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        $row = Database::query(
            'SELECT * FROM data_backups WHERE id = ? AND (school_id = ? OR school_id IS NULL)',
            [$id, $schoolId]
        )->fetch();

        if (!$row) {
            $this->respond(false, 'Backup not found.', 'security');
        }

        if (is_file($row['file_path'])) {
            @unlink($row['file_path']);
        }

        Database::conn()->prepare('DELETE FROM data_backups WHERE id = ?')->execute([$id]);
        audit('security', 'delete', 'data_backups', $id, 'Deleted backup ' . basename($row['file_path']));

        $this->respond(true, 'Backup deleted.', 'security');
    }
}
