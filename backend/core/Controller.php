<?php
declare(strict_types=1);

/**
 * Base controller: rendering, responses and permission gating.
 *
 * Route permissions are declared in index.php, but actions that need a finer
 * check call allowed() as well.
 */
abstract class Controller
{
    /**
     * Render a view inside the main layout (or the bare auth layout).
     *
     * $data is extracted into local variables for the template. The request
     * ends here, matching how the router expects a single response.
     */
    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        security_headers();
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__, 2) . '/frontend/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = dirname(__DIR__, 2) . '/frontend/layout/' . ($layout === 'bare' ? 'auth' : 'app') . '.php';
        require $layoutFile;
        exit;
    }

    /**
     * Finish an action: JSON for AJAX callers, otherwise a flash message and
     * redirect. Validation errors are attached to the JSON response only.
     */
    protected function respond(
        bool $ok,
        string $message,
        string $back,
        array $params = [],
        array $errors = []
    ): never {
        if (expects_json()) {
            json_out([
                'ok'       => $ok,
                'message'  => $message,
                'redirect' => $ok ? url($back, $params) : null,
                'errors'   => $errors,
            ], $ok ? 200 : 422);
        }
        flash_set($ok ? 'success' : 'danger', $message);
        redirect($back, $params);
    }

    /**
     * Check a module permission; when denied, respond with an error and
     * return false so the caller can stop.
     */
    protected function allowed(string $module, string $action = 'view'): bool
    {
        if (Auth::can($module, $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this ' . $module . ' action.', 'dashboard');
        return false;
    }
}
