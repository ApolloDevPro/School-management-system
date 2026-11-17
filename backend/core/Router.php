<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $route, array $handler, array $options = []): void
    {
        $this->routes['GET ' . $route] = ['handler' => $handler, 'options' => $options];
    }

    public function post(string $route, array $handler, array $options = []): void
    {
        $this->routes['POST ' . $route] = ['handler' => $handler, 'options' => $options];
    }

    public function dispatch(): void
    {
        $name   = (string)($_GET['r'] ?? 'dashboard');
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $entry  = $this->routes[$method . ' ' . $name] ?? null;

        if ($entry === null) {
            $this->reject('This page does not exist.', 404, 'dashboard');
            return;
        }

        if ($method === 'POST') {
            $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Csrf::check($token)) {
                $this->reject('Your session expired. Refresh the page and try again.', 419, Auth::check() ? 'dashboard' : 'login');
            }
        }

        $options = $entry['options'];

        $publicRoutes = ['login','install','forgot-password','reset-password','two-factor/challenge','two-factor/verify'];
        $allowWhenMustChange = ['change-password','change-password/save','logout'];

        if (($options['auth'] ?? false) && !Auth::check()) {
            if (expects_json()) {
                json_out(['ok' => false, 'message' => 'Authentication required.'], 401);
            }
            redirect('login');
        }

        if (Auth::mustChangePassword() && !in_array($name, $allowWhenMustChange, true)) {
            if (expects_json()) {
                json_out(['ok' => false, 'message' => 'You must change your password before continuing.', 'redirect' => url('change-password')], 403);
            }
            redirect('change-password');
        }

        if (isset($options['perm']) && !Auth::can($options['perm'][0], $options['perm'][1])) {
            $this->reject('You do not have permission to perform this action.', 403, 'dashboard');
        }

        [$class, $action] = $entry['handler'];
        $controller = new $class();
        $controller->$action();
    }

    private function reject(string $message, int $status, string $fallback): never
    {
        security_headers();
        if (expects_json()) {
            json_out(['ok' => false, 'message' => $message, 'errors' => []], $status);
        }
        if ($fallback === 'dashboard' && !Auth::check()) {
            $fallback = 'login';
        }
        flash_set('warning', $message);
        redirect($fallback);
    }
}
