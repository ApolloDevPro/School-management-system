<?php
declare(strict_types=1);

/**
 * Global helpers shared by controllers, models and views.
 *
 * Escaping, URL building, redirects, JSON responses, flash messages, CSRF
 * fields, school context, security headers and the audit trail live here.
 */

/**
 * Escape a value for HTML output. Use this for every dynamic string printed
 * inside a view.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Build an application URL for a route, e.g. url('students', ['id' => 4]).
 */
function url(string $route = 'dashboard', array $params = []): string
{
    $url = BASE_URL . '/index.php?r=' . urlencode($route);
    foreach ($params as $key => $value) {
        $url .= '&' . urlencode((string)$key) . '=' . urlencode((string)$value);
    }
    return $url;
}

/**
 * URL for a file under the project root (css, js, uploads, ...).
 */
function asset(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Send a redirect and stop the request.
 */
function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

/**
 * True when the caller expects JSON (AJAX header or JSON Accept header).
 */
function expects_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/**
 * Emit a JSON response, security headers included, and stop the request.
 */
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    security_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Queue a one-time message for the next rendered page.
 */
function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Take and clear all queued flash messages.
 */
function flash_all(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/**
 * Hidden CSRF input for a form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/**
 * Best-effort client address for audit records.
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * The installation's school row, cached for the request.
 */
function school_row(): ?array
{
    static $row = null;
    if ($row === null) {
        $row = Database::conn()->query('SELECT * FROM schools ORDER BY id LIMIT 1')->fetch() ?: null;
    }
    return $row;
}

/**
 * School scope for queries: the signed-in user's school, falling back to the
 * single school row on public pages (login, install).
 */
function school_id(): int
{
    $user = Auth::user();
    if ($user) {
        return (int)$user['school_id'];
    }
    $school = school_row();
    if ($school) {
        return (int)$school['id'];
    }
    throw new RuntimeException('No school context available. Please configure a school or sign in.');
}

/**
 * Effective minimum password length from settings, clamped to 8-64.
 */
function password_min_length(): int
{
    try {
        $min = (int)Setting::get(school_id(), 'security.password_min_length', 8);
    } catch (Throwable) {
        $min = 8;
    }
    return max(8, min(64, $min));
}

/**
 * Name of the active term, or "No active term".
 */
function current_term_name(): string
{
    static $name = null;
    if ($name === null) {
        $statement = Database::conn()->prepare(
            'SELECT t.name FROM terms t
               JOIN academic_years y ON y.id = t.academic_year_id
              WHERE y.school_id = ? AND t.is_active = 1
              ORDER BY t.id LIMIT 1'
        );
        $statement->execute([school_id()]);
        $name = (string)($statement->fetchColumn() ?: 'No active term');
    }
    return $name;
}

/**
 * Baseline response headers, including a CSP that allows the bundled CDNs.
 */
function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $csp = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net https://code.jquery.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net; "
        . "img-src 'self' data: https:; "
        . "font-src 'self' https:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none';";
    header('Content-Security-Policy: ' . $csp);
}

/**
 * Append an audit trail entry for the current actor.
 *
 * Old and new values are stored as JSON so a change can be reconstructed.
 */
function audit(
    string $module,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    string $summary = '',
    ?array $old = null,
    ?array $new = null
): void {
    $user = Auth::user();
    $schoolId = null;
    if ($user) {
        $schoolId = (int)$user['school_id'];
    } else {
        $school = school_row();
        $schoolId = $school ? (int)$school['id'] : null;
    }
    AuditLog::record([
        'school_id'   => $schoolId,
        'user_id'     => $user['id'] ?? null,
        'user_name'   => $user['full_name'] ?? null,
        'role_name'   => $user['role_name'] ?? null,
        'module'      => $module,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'summary'     => mb_substr($summary, 0, 255),
        'old_values'  => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
        'new_values'  => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
        'ip_address'  => client_ip(),
        'user_agent'  => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}
