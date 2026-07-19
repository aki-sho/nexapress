<?php

namespace app\Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data);

        $appViewPath = BASE_PATH . '/app/Views/' . $view . '.php';

        if ($view === 'admin/login') {
            if (!file_exists($appViewPath)) {
                echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
                return;
            }

            ob_start();
            require $appViewPath;
            $content = ob_get_clean();

            require BASE_PATH . '/app/Views/admin/login-layout.php';
            return;
        }

        if (str_starts_with($view, 'admin/')) {
            if (!file_exists($appViewPath)) {
                echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
                return;
            }

            ob_start();
            require $appViewPath;
            $content = ob_get_clean();

            require BASE_PATH . '/app/Views/admin/layout.php';
            return;
        }

        if (
            $view === 'install' ||
            str_starts_with($view, 'install-')
        ) {
            if (!file_exists($appViewPath)) {
                echo 'View not found: '
                    . htmlspecialchars(
                        $view,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                return;
            }

            ob_start();

            require $appViewPath;

            $content = ob_get_clean();

            require BASE_PATH
                . '/app/Views/install-layout.php';

            return;
        }

        $themeViewPath = Theme::template($view);
        $viewPath = $themeViewPath ?: $appViewPath;

        if (!file_exists($viewPath)) {
            echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        $themeLayoutPath = Theme::template('layout');

        if ($themeLayoutPath) {
            require $themeLayoutPath;
            return;
        }

        require BASE_PATH . '/app/Views/layout.php';
    }
}