<?php
declare(strict_types=1);

/**
 * Command-line runner for scheduled tasks.
 *
 * Run from Windows Task Scheduler or cron:
 *   C:\xampp\php\php.exe "C:\xampp\htdocs\school mgt system\cron.php"
 *
 * The script is CLI-only, takes a non-blocking lock so two runners cannot
 * overlap, and prints one line per job. It exits with status 1 when any job
 * fails so the scheduler can alert on the run.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "This script runs from the command line only.\n";
    exit;
}

require __DIR__ . '/backend/config/config.php';
require __DIR__ . '/backend/core/helpers.php';

spl_autoload_register(function (string $class): void {
    foreach (['core', 'controllers', 'models', 'lib'] as $folder) {
        $file = __DIR__ . '/backend/' . $folder . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

$lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ssms_cron_' . substr(md5(__DIR__), 0, 12) . '.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, "Another scheduled-task run is already in progress.\n");
    exit(0);
}

$started = microtime(true);
try {
    $results = CronScheduler::runDueJobs();
} catch (Throwable $error) {
    fwrite(STDERR, 'Scheduler failed: ' . $error->getMessage() . "\n");
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

if (!$results) {
    fwrite(STDOUT, "No scheduled tasks were due at " . date('Y-m-d H:i:s') . ".\n");
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$failed = 0;
foreach ($results as $result) {
    if ($result['status'] === 'completed') {
        fwrite(STDOUT, '[OK] ' . $result['job'] . "\n");
    } else {
        $failed++;
        fwrite(STDOUT, '[FAIL] ' . $result['job'] . ': ' . ($result['error'] ?? 'unknown error') . "\n");
    }
}
$seconds = number_format(microtime(true) - $started, 2);
fwrite(STDOUT, count($results) . ' task(s) processed in ' . $seconds . "s.\n");

flock($lock, LOCK_UN);
fclose($lock);
exit($failed > 0 ? 1 : 0);
