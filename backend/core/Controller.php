<?php
declare(strict_types=1);

abstract class Controller
{
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

    protected function allowed(string $module, string $action = 'view'): bool
    {
        if (Auth::can($module, $action)) {
            return true;
        }
        $this->respond(false, 'You do not have permission to access this ' . $module . ' action.', 'dashboard');
        return false;
    }
}
