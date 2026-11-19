<?php
declare(strict_types=1);

/**
 * Minimal route table for index.php?r=<route>.
 *
 * Each route stores its controller action plus options such as
 * ['auth' => true, 'perm' => [module, action]]. Dispatch applies, in order:
 * CSRF on POST, authentication, forced password change, portal confinement
 * and the module permission check.
 */
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

    /**
     * Resolve the current request, enforce its guards and run the action.
     */
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
                // Apache maps the non-standard 419 status to 500, so send 403.
                $this->reject('Your session expired. Refresh the page and try again.', 403, Auth::check() ? 'dashboard' : 'login');
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

        // Session records can be revoked from the admin screen, so every
        // signed-in request is checked against the server-side row.
        if (Auth::check()) {
            $ended = Auth::guardSession();
            if ($ended !== null) {
                if (expects_json()) {
                    json_out(['ok' => false, 'message' => $ended], 401);
                }
                flash_set('warning', $ended);
                redirect('login');
            }
        }

        if (Auth::mustChangePassword() && !in_array($name, $allowWhenMustChange, true)) {
            if (expects_json()) {
                json_out(['ok' => false, 'message' => 'You must change your password before continuing.', 'redirect' => url('change-password')], 403);
            }
            redirect('change-password');
        }

        // Parent and student accounts are self-service only. Staff modules expose
        // school-wide data, so portal users are confined to their own pages.
        if (Auth::check() && in_array(Auth::user()['user_type'] ?? '', ['parent', 'student'], true)) {
            $portalRoutes = ['portal', 'portal/consent/update', 'profile', 'profile/update',
                             'change-password', 'change-password/save', 'logout'];
            if (!in_array($name, $portalRoutes, true)) {
                if (expects_json()) {
                    json_out(['ok' => false, 'message' => 'Portal accounts can only access the portal.', 'redirect' => url('portal')], 403);
                }
                flash_set('warning', 'Your account can only access the portal.');
                redirect('portal');
            }
        }

        if (isset($options['perm']) && !Auth::can($options['perm'][0], $options['perm'][1])) {
            $this->reject('You do not have permission to perform this action.', 403, 'dashboard');
        }

        [$class, $action] = $entry['handler'];
        $controller = new $class();
        $controller->$action();
    }

    /**
     * Stop the request: JSON for API callers, otherwise a flash message and
     * redirect to a safe page.
     */
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
