<?php

namespace App\Core;

class Router
{
    // Sentinela que uma middleware devolve quando ja tratou a resposta
    // (ex: ErrorPage::forbidden()) — nao despachar pro controller nem
    // redirecionar. Nunca colide com uma Location de verdade.
    const HALT = "\0__ROUTER_HALT__\0";

    private $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $path = $this->normalizePath($path);

        $this->routes[$method][] = [
            'path' => $path,
            'pattern' => $this->buildPattern($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $result = $this->resolve($method, $uri);

        if ($result['action'] === 'not_found') {
            ErrorPage::notFound();
            return;
        }

        if ($result['action'] === 'halt') {
            return;
        }

        if ($result['action'] === 'redirect') {
            header('Location: ' . $result['location']);
            return;
        }

        [$controllerClass, $action] = $result['handler'];
        $controller = new $controllerClass();
        $controller->$action(...$result['params']);
    }

    /**
     * Resolve a rota e roda a cadeia de middleware, sem efeitos colaterais
     * (nem header(), nem instancia o controller) — usado pelo dispatch()
     * de verdade e pelos testes, que inspecionam o resultado direto.
     */
    public function resolve(string $method, string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $this->normalizePath($path);
        $method = $method === 'HEAD' ? 'GET' : $method;
        $matchedRoute = $this->match($method, $path);

        if ($matchedRoute === null) {
            return $this->result('not_found');
        }

        foreach ($matchedRoute['middleware'] as $middlewareEntry) {
            $middleware = is_string($middlewareEntry) ? new $middlewareEntry() : $middlewareEntry;
            $outcome = $middleware->handle($matchedRoute['namedParams']);

            if ($outcome === self::HALT) {
                return $this->result('halt');
            }

            if ($outcome !== null) {
                return $this->result('redirect', null, [], [], $outcome);
            }
        }

        return $this->result('dispatch', $matchedRoute['handler'], $matchedRoute['params'], $matchedRoute['namedParams']);
    }

    private function result(string $action, ?array $handler = null, array $params = [], array $namedParams = [], ?string $location = null): array
    {
        return [
            'action' => $action,
            'handler' => $handler,
            'params' => $params,
            'namedParams' => $namedParams,
            'location' => $location,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }

    private function match(string $method, string $path): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }

            $params = [];
            $namedParams = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = $value;
                    $namedParams[$key] = $value;
                }
            }

            return [
                'handler' => $route['handler'],
                'middleware' => $route['middleware'],
                'params' => $params,
                'namedParams' => $namedParams,
            ];
        }

        return null;
    }
}
