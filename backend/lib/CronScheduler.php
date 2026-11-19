<?php
declare(strict_types=1);

/**
 * Runner and bookkeeping for cron_jobs rows created under
 * Support & security -> Scheduled tasks.
 *
 * The app itself only runs jobs on demand. Call runDueJobs() from an OS cron
 * task or a small CLI script if you want automatic execution.
 */
final class CronScheduler
{
    private const DUE_TABLE = 'cron_jobs';
    private const LOG_TABLE = 'cron_job_logs';

    /**
     * Active jobs whose next_run_at has passed (or was never set).
     */
    public static function dueJobs(): array
    {
        $db = Database::conn();
        $now = date('Y-m-d H:i:s');
        return Database::query(
            "SELECT * FROM " . self::DUE_TABLE . "
               WHERE is_active = 1
                 AND (next_run_at IS NULL OR next_run_at <= ?)
               ORDER BY next_run_at ASC, id ASC
               LIMIT 20",
            [$now]
        )->fetchAll();
    }

    /**
     * Mark a job as running so a concurrent runner does not pick it up.
     */
    public static function markRunning(int $jobId): void
    {
        $db = Database::conn();
        $db->prepare(
            "UPDATE " . self::DUE_TABLE . "
               SET last_run_at = NOW(), last_run_status = 'running'
             WHERE id = ?"
        )->execute([$jobId]);
    }

    /**
     * Record success and schedule the next run based on the job interval.
     */
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

    /**
     * Record a failure and increment the failure counter. The job stays active
     * so it can be retried on the next run.
     */
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

    /**
     * Append a run log entry with its duration.
     */
    public static function log(int $jobId, string $status, string $message, int $durationMs = 0): void
    {
        $db = Database::conn();
        $db->prepare(
            "INSERT INTO " . self::LOG_TABLE . " (job_id, status, message, duration_ms, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$jobId, $status, $message, $durationMs]
        )->execute();
    }

    /**
     * Run every due job once. Each job is marked running, its handler is
     * instantiated and called with (job id, job row), then the outcome is
     * recorded. One failure does not stop the remaining jobs.
     */
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
