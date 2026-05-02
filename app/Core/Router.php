<?php

namespace app\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, string $action): void
    {
        $this->routes['GET'][$path] = $action;
    }

    public function post(string $path, string $action): void
    {
        $this->routes['POST'][$path] = $action;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

        if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }

        $uri = rtrim($uri, '/');

        if ($uri === '') {
            $uri = '/';
        }

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $path => $action) {
            $pattern = $this->convertToPattern($path);

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->runAction($action, $matches);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function convertToPattern(string $path): string
    {
        $pattern = preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $path);

        return '#^' . $pattern . '$#';
    }

    private function runAction(string $action, array $params = []): void
    {
        [$controllerName, $methodName] = explode('@', $action);

        $controller = new $controllerName();

        call_user_func_array([$controller, $methodName], $params);
    }
}