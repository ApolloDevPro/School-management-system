<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $route = 'dashboard', array $params = []): string
{
    $url = BASE_URL . '/index.php?r=' . urlencode($route);
    foreach ($params as $key => $value) {
        $url .= '&' . urlencode((string)$key) . '=' . urlencode((string)$value);
    }
    return $url;
}

function asset(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function expects_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    security_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_all(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function school_row(): ?array
{
    static $row = null;
    if ($row === null) {
        $row = Database::conn()->query('SELECT * FROM schools ORDER BY id LIMIT 1')->fetch() ?: null;
    }
    return $row;
}

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

function current_term_name(): string
{
    static $name = null;
    if ($name === null) {
        $name = (string)(Database::conn()
            ->query('SELECT t.name FROM terms t WHERE t.is_active = 1 ORDER BY t.id LIMIT 1')
            ->fetchColumn() ?: 'No active term');
    }
    return $name;
}

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
