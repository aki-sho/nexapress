<?php

namespace app\Core;

class Router
{
    private array $routes = [];
    private array $contentRoutes = [];

    public function get(string $path, string $action): void
    {
        $this->routes['GET'][$path] = $action;
        unset($this->contentRoutes[$path]);
    }

    public function getContent(string $path, string $action): void
    {
        $this->get($path, $action);
        $this->contentRoutes[$path] = true;
    }

    public function post(string $path, string $action): void
    {
        $this->routes['POST'][$path] = $action;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (BASE_URL !== '' && str_starts_with($uri, BASE_URL)) {
            $uri = substr($uri, strlen(BASE_URL));
        }

        $uri = rtrim($uri, '/');

        if ($uri === '') {
            $uri = '/';
        }

        $route = $this->match($this->routes[$method] ?? [], $uri);
        if ($route === null && $method === 'POST' && ContentExtension::handlesPost($_POST['_nx_content_action'] ?? null)) {
            // Resolve with the complete GET table so reserved and extension URLs are not shadowed.
            $candidate = $this->match($this->routes['GET'] ?? [], $uri);
            if ($candidate !== null && isset($this->contentRoutes[$candidate[0]])) {
                $route = $candidate;
            }
        }
        if ($route !== null) {
            [$path, $action, $matches] = $route;
            Debug::log('Route matched', [
                'method' => $method,
                'uri' => $uri,
                'route' => $path,
                'action' => $action,
                'params' => $matches,
            ]);
            $this->runAction($action, $matches);
            return;
        }

        Debug::log('Route not found', [
            'method' => $method,
            'uri' => $uri,
        ]);

        http_response_code(404);
        echo '404 Not Found';
    }

    private function match(array $routes, string $uri): ?array
    {
        foreach ($routes as $path => $action) {
            if (preg_match($this->convertToPattern($path), $uri, $matches)) {
                return [$path, $action, array_map('rawurldecode', array_slice($matches, 1))];
            }
        }
        return null;
    }

    private function convertToPattern(string $path): string
    {
        $pattern = preg_replace('/\{[a-zA-Z_]+\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function runAction(
        string $action,
        array $params = []
    ): void {
        [$controllerName, $methodName] =
            explode('@', $action);

        /*
        * 管理画面の権限を確認
        */
        Permission::authorizeAction(
            $controllerName,
            $methodName,
            $params
        );

        $controller =
            new $controllerName();

        call_user_func_array(
            [$controller, $methodName],
            $params
        );
    }
}
