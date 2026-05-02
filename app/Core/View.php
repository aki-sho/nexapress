<?php

namespace app\Core;

class View
{
    public static function render(string $view, array $data = []): void
    {
        extract($data);

        $viewPath = BASE_PATH . '/app/Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            echo 'View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        require $viewPath;
    }
}