<?php

declare(strict_types=1);

namespace App;

/**
 * Router mínimo. Patrones con parámetros nombrados: "/marcha/{slugAndId}".
 * El handler recibe un array asociativo con los parámetros capturados.
 */
final class Router
{
    /** @var list<array{method:string,regex:string,params:list<string>,handler:callable}> */
    private array $routes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
        ];
    }

    public function notFound(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rawurldecode($path);
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $m)) {
                $args = [];
                foreach ($route['params'] as $i => $name) {
                    $args[$name] = $m[$i + 1] ?? null;
                }
                ($route['handler'])($args);
                return;
            }
        }

        http_response_code(404);
        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)();
        } else {
            echo '404 Not Found';
        }
    }
}
