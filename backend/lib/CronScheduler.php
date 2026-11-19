<?php
declare(strict_types=1);

final class CronScheduler
{
    private const DUE_TABLE = 'cron_jobs';
    private const LOG_TABLE = 'cron_job_logs';

    public static function dueJobs(): array
    {
        $db = Database::conn();
        $now = date('Y-m-d H:i:s');
        return $db->query(
            "SELECT * FROM " . self::DUE_TABLE . "
               WHERE is_active = 1
                 AND (next_run_at IS NULL OR next_run_at <= ?)
               ORDER BY next_run_at ASC, id ASC
               LIMIT 20",
            [$now]
        )->fetchAll();
    }

    public static function markRunning(int $jobId): void
    {
        $db = Database::conn();
        $db->prepare(
            "UPDATE " . self::DUE_TABLE . "
               SET last_run_at = NOW(), last_run_status = 'running'
             WHERE id = ?",
            [$jobId]
        )->execute();
    }

    public static function markCompleted(int $jobId, string $message = '', int $nextOffsetMinutes = 0): void
    {
        $db = Database::conn();
        $nextRun = $nextOffsetMinutes > 0
            ? date('Y-m-d H:i:s', strtotime("+{$nextOffsetMinutes} minutes"))
            : null;
        $db->prepare(
            "UPDATE " . self::DUE_TABLE . "
               SET last_run_at = NOW(),
                   last_run_status = 'completed',
                   last_run_message = ?,
                   next_run_at = ?,
                   run_count = run_count + 1
             WHERE id = ?",
            [$message, $nextRun, $jobId]
        )->execute();
    }

    public static function markFailed(int $jobId, string $error): void
    {
        $db = Database::conn();
        $db->prepare(
            "UPDATE " . self::DUE_TABLE . "
               SET last_run_at = NOW(),
                   last_run_status = 'failed',
                   last_run_message = ?,
                   failure_count = failure_count + 1
             WHERE id = ?",
            [$error, $jobId]
        )->execute();
    }

    public static function log(int $jobId, string $status, string $message, int $durationMs = 0): void
    {
        $db = Database::conn();
        $db->prepare(
            "INSERT INTO " . self::LOG_TABLE . " (job_id, status, message, duration_ms, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$jobId, $status, $message, $durationMs]
        )->execute();
    }

    public static function runDueJobs(): array
    {
        $results = [];
        foreach (self::dueJobs() as $job) {
            $start = microtime(true);
            self::markRunning((int)$job['id']);
            try {
                $handler = $job['handler_class'] ?? '';
                $method = $job['handler_method'] ?? 'run';
                if ($handler === '' || !class_exists($handler)) {
                    throw new Exception('Handler class not found: ' . $handler);
                }
                $instance = new $handler();
                if (!method_exists($instance, $method)) {
                    throw new Exception('Handler method not found: ' . $handler . '::' . $method);
                }
                $instance->$method((int)$job['id'], $job);
                $durationMs = (int)((microtime(true) - $start) * 1000);
                self::markCompleted((int)$job['id'], 'Completed successfully', (int)($job['interval_minutes'] ?? 0));
                self::log((int)$job['id'], 'success', 'Job completed successfully', $durationMs);
                $results[] = ['job' => $job['name'], 'status' => 'completed'];
            } catch (Throwable $error) {
                $durationMs = (int)((microtime(true) - $start) * 1000);
                self::markFailed((int)$job['id'], $error->getMessage());
                self::log((int)$job['id'], 'error', $error->getMessage(), $durationMs);
                $results[] = ['job' => $job['name'], 'status' => 'failed', 'error' => $error->getMessage()];
            }
        }
        return $results;
    }
}
