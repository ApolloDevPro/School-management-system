<?php
declare(strict_types=1);

final class CronController extends Controller
{
    public function index(): void
    {
        if (!$this->allowed('security', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $jobs = $db->query(
            'SELECT * FROM cron_jobs WHERE (school_id = ? OR school_id IS NULL)
             ORDER BY is_active DESC, next_run_at ASC, name',
            [$schoolId]
        )->fetchAll();

        $logs = $db->query(
            'SELECT cjl.*, cj.name AS job_name
               FROM cron_job_logs cjl
               JOIN cron_jobs cj ON cj.id = cjl.job_id
              WHERE (cj.school_id = ? OR cj.school_id IS NULL)
              ORDER BY cjl.created_at DESC
              LIMIT 100',
            [$schoolId]
        )->fetchAll();

        $this->render('system/cron_jobs', [
            'pageTitle' => 'Scheduled Tasks',
            'activeNav' => 'security',
            'jobs' => $jobs,
            'logs' => $logs,
        ]);
    }

    public function save(): void
    {
        if (!$this->allowed('security', 'edit')) {
            return;
        }
        $schoolId = school_id();
        $db = Database::conn();

        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $handlerClass = trim((string)($_POST['handler_class'] ?? ''));
        $handlerMethod = trim((string)($_POST['handler_method'] ?? 'run'));
        $intervalMinutes = filter_var($_POST['interval_minutes'] ?? '60', FILTER_VALIDATE_INT);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 140
            || mb_strlen($description) > 255
            || $handlerClass === '' || !class_exists($handlerClass)
            || !method_exists($handlerClass, $handlerMethod)
            || mb_strlen($handlerMethod) > 60
            || $intervalMinutes === false || $intervalMinutes < 1 || $intervalMinutes > 525600) {
            $this->respond(false, 'Enter valid scheduled task details.', 'security/backups');
            return;
        }

        $nextRun = date('Y-m-d H:i:s', strtotime("+{$intervalMinutes} minutes"));

        $db->beginTransaction();
        try {
            if ($id > 0) {
                $exists = $db->query(
                    'SELECT id FROM cron_jobs WHERE id = ? AND (school_id = ? OR school_id IS NULL)',
                    [$id, $schoolId]
                )->fetch();
                if (!$exists) {
                    throw new DomainException('Scheduled task not found.');
                }
                $db->prepare(
                    'UPDATE cron_jobs SET name = ?, description = ?, handler_class = ?,
                        handler_method = ?, interval_minutes = ?, next_run_at = ?, is_active = ?
                      WHERE id = ?'
                )->execute([
                    $name, $description, $handlerClass, $handlerMethod,
                    $intervalMinutes, $nextRun, $isActive, $id
                ]);
                audit('security', 'update_cron_job', 'cron_jobs', $id, 'Updated scheduled task: ' . $name);
                $this->respond(true, 'Scheduled task updated.', 'security/backups');
            } else {
                $db->prepare(
                    'INSERT INTO cron_jobs
                        (school_id, name, description, handler_class, handler_method,
                         interval_minutes, next_run_at, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $schoolId, $name, $description, $handlerClass, $handlerMethod,
                    $intervalMinutes, $nextRun, $isActive,
                    (int)(Auth::user()['id'] ?? 0)
                ]);
                $newId = (int)$db->lastInsertId();
                audit('security', 'create_cron_job', 'cron_jobs', $newId, 'Created scheduled task: ' . $name);
                $this->respond(true, 'Scheduled task created.', 'security/backups');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->respond(false, $error->getMessage(), 'security/backups');
        }
    }

    public function toggle(): void
    {
        if (!$this->allowed('security', 'edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $schoolId = school_id();

        $db = Database::conn();
        $db->prepare(
            'UPDATE cron_jobs SET is_active = ? WHERE id = ? AND (school_id = ? OR school_id IS NULL)',
            [$isActive, $id, $schoolId]
        )->execute();
        audit('security', 'toggle_cron_job', 'cron_jobs', $id, 'Scheduled task ' . ($isActive ? 'activated' : 'deactivated'));
        $this->respond(true, 'Scheduled task ' . ($isActive ? 'activated' : 'deactivated') . '.', 'security/backups');
    }

    public function runJob(): void
    {
        if (!$this->allowed('security', 'edit')) {
            return;
        }
        $id = (int)($_POST['id'] ?? 0);
        $schoolId = school_id();

        $db = Database::conn();
        $job = $db->query(
            'SELECT * FROM cron_jobs WHERE id = ? AND (school_id = ? OR school_id IS NULL) AND is_active = 1',
            [$id, $schoolId]
        )->fetch();
        if (!$job) {
            $this->respond(false, 'Active scheduled task not found.', 'security/backups');
            return;
        }

        $start = microtime(true);
        $db->prepare(
            'UPDATE cron_jobs SET last_run_at = NOW(), last_run_status = \'running\' WHERE id = ?'
        )->execute([$id]);

        try {
            $handlerClass = $job['handler_class'];
            $handlerMethod = $job['handler_method'] ?? 'run';
            $instance = new $handlerClass();
            if (!method_exists($instance, $handlerMethod)) {
                throw new DomainException('Handler method not found: ' . $handlerClass . '::' . $handlerMethod);
            }
            $instance->$handlerMethod($id, $job);
            $durationMs = (int)((microtime(true) - $start) * 1000);
            $nextRun = date('Y-m-d H:i:s', strtotime("+{$job['interval_minutes']} minutes"));
            $db->prepare(
                'UPDATE cron_jobs SET last_run_at = NOW(), last_run_status = \'completed\',
                    last_run_message = ?, next_run_at = ?, run_count = run_count + 1
                  WHERE id = ?'
            )->execute(['Completed successfully', $nextRun, $id]);
            $db->prepare(
                'INSERT INTO cron_job_logs (job_id, status, message, duration_ms, created_at)
                 VALUES (?, \'success\', ?, ?, NOW())'
            )->execute([$id, 'Job completed successfully', $durationMs]);
            $this->respond(true, 'Scheduled task executed successfully.', 'security/backups');
        } catch (Throwable $error) {
            $durationMs = (int)((microtime(true) - $start) * 1000);
            $db->prepare(
                'UPDATE cron_jobs SET last_run_at = NOW(), last_run_status = \'failed\',
                    last_run_message = ?, failure_count = failure_count + 1
                  WHERE id = ?'
            )->execute([$error->getMessage(), $id]);
            $db->prepare(
                'INSERT INTO cron_job_logs (job_id, status, message, duration_ms, created_at)
                 VALUES (?, \'error\', ?, ?, NOW())'
            )->execute([$id, $error->getMessage(), $durationMs]);
            $this->respond(false, 'Task failed: ' . $error->getMessage(), 'security/backups');
        }
    }

    protected function allowed(string $module = 'security', string $action = 'view'): bool
    {
        if (Auth::can($module, $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to manage scheduled tasks.', 'dashboard');
        return false;
    }
}
