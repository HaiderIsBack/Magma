<?php

namespace Magma\Routing;

final class Router
{
    /**
     * Registered routes.
     *
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     pattern: string,
     *     parameters: array<int, string>,
     *     handler: mixed
     * }>
     */
    private array $routes = [];

    /**
     * Register a route.
     *
     * The handler may be a callable or a controller definition,
     * such as [UserController::class, 'show'].
     */
    public function add(
        string $method,
        string $path,
        mixed $handler
    ): void {
        $method = strtoupper($method);

        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException(
                'Route paths must start with /.'
            );
        }

        [$pattern, $parameters] = $this->compilePath($path);

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'parameters' => $parameters,
            'handler' => $handler,
        ];
    }

    /**
     * Register a route definition.
     */
    public function addRoute(Route $route): void
    {
        $this->add(
            $route->method,
            $route->path,
            [$route->controller, $route->action]
        );
    }

    /**
     * Match an incoming request against registered routes.
     *
     * Returns the handler and named parameters, or null if
     * no route matches.
     */
    public function match(
        string $method,
        string $uri
    ): ?array {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match(
                $route['pattern'],
                $path,
                $matches
            )) {
                continue;
            }

            $params = [];

            foreach ($route['parameters'] as $name) {
                $params[$name] = $matches[$name];
            }

            return [
                'handler' => [
                    'controller' => $route['handler'][0],
                    'action' => $route['handler'][1]
                ],
                'params' => $params,
            ];
        }

        return null;
    }

    /**
     * Convert /users/{id} or /users/:id into a regular expression.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function compilePath(string $path): array
    {
        $parameters = [];
        $pattern = '';
        $offset = 0;

        preg_match_all(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}|:([a-zA-Z_][a-zA-Z0-9_]*)/',
            $path,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        foreach ($matches[0] as $index => $match) {
            [$placeholder, $position] = $match;

            $name = $matches[1][$index][0] !== ''
                ? $matches[1][$index][0]
                : $matches[2][$index][0];

            if (in_array($name, $parameters, true)) {
                throw new \InvalidArgumentException(
                    "Duplicate route parameter: {$name}"
                );
            }

            $pattern .= preg_quote(
                substr($path, $offset, $position - $offset),
                '#'
            );

            $pattern .= '(?P<' . $name . '>[^/]+)';

            $parameters[] = $name;
            $offset = $position + strlen($placeholder);
        }

        $pattern .= preg_quote(substr($path, $offset), '#');

        return [
            '#^' . $pattern . '$#D',
            $parameters,
        ];
    }
}
