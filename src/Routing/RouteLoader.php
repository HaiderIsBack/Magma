<?php

namespace Magma\Routing;

use ReflectionClass;
use Magma\Routing\Route;
use Magma\Http\Get;
use RuntimeException;

final class RouteLoader
{
    public function load(array $controllers): array
    {
        $routes = [];

        foreach ($controllers as $controller) {
            $reflection = new ReflectionClass($controller);

            foreach ($reflection->getMethods() as $method) {
                if (
                    $method->isStatic()
                    || !$method->isPublic()
                    || $method->isConstructor()
                ) {
                    continue;
                }

                // Prevent accidentally registering inherited
                // methods as endpoints.
                if (
                    $method->getDeclaringClass()->getName()
                    !== $controller
                ) {
                    continue;
                }

                foreach ($method->getAttributes(Get::class) as $attribute) {
                    $route = $attribute->newInstance();

                    $routes[] = new Route(
                        'GET',
                        $route->path,
                        $controller,
                        $method->getName()
                    );
                }
            }
        }

        $this->validateRoutes($routes);

        return $routes;
    }

    private function validateRoutes(array $routes): void
    {
        $registered = [];

        foreach ($routes as $route) {
            $key = $route->method . ' ' . $route->path;

            if (isset($registered[$key])) {
                throw new RuntimeException(
                    "Duplicate route: {$key}"
                );
            }

            $registered[$key] = true;
        }
    }
}