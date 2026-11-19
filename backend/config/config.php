<?php
declare(strict_types=1);

/**
 * Application configuration.
 *
 * Parses the root .env file (if present) into $_ENV/$_SERVER, then defines
 * the constants used across the backend. Real environment variables take
 * precedence over .env values, and defaults apply when a key is missing.
 */

// Load environment variables from .env file if present
$envFile = __DIR__ . '/../../.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip surrounding quotes
            if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
                $value = $m[2];
            }
            if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Database connection
define('DB_HOST', $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'ssms');
define('DB_USER', $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '');

// Application identity and runtime
define('APP_NAME', $_ENV['APP_NAME'] ?? $_SERVER['APP_NAME'] ?? 'SSMS');
define('APP_TAGLINE', $_ENV['APP_TAGLINE'] ?? $_SERVER['APP_TAGLINE'] ?? 'Secondary School Management System');
define('APP_TIMEZONE', $_ENV['APP_TIMEZONE'] ?? $_SERVER['APP_TIMEZONE'] ?? 'Africa/Kampala');
define('APP_URL', rtrim($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? '', '/'));
define('APP_ENV', strtolower($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production'));

// Base path of the installation (e.g. /school mgt system), derived from index.php
define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

date_default_timezone_set(APP_TIMEZONE);
