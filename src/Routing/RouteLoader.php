<?php

namespace Magma\Routing;

use ReflectionClass;
use Magma\Routing\Route;
use Magma\Http\Group;
use Magma\Http\Get;
use Magma\Http\Post;
use Magma\Http\Put;
use Magma\Http\Patch;
use Magma\Http\Delete;
use RuntimeException;

final class RouteLoader
{
    public function load(array $controllers): array
    {
        $routes = [];

        $attributeMappings = [
            Get::class => 'GET',
            Post::class => 'POST',
            Put::class => 'PUT',
            Patch::class => 'PATCH',
            Delete::class => 'DELETE',
        ];

        foreach ($controllers as $controller) {
            $reflection = new ReflectionClass($controller);

            $groupAttributes = $reflection->getAttributes(Group::class);

            $prefix = '';

            if ($groupAttributes !== []) {
                $group = $groupAttributes[0]->newInstance();
                $prefix = $group->prefix;
            }

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

                foreach ($attributeMappings as $attributeClass => $httpMethod) {
                    foreach ($method->getAttributes($attributeClass) as $attribute) {
                        $route = $attribute->newInstance();

                        $path = $route->path;

                        if (trim($prefix) !== '') {
                            $path = '/' . trim(
                                $prefix . '/' . ltrim($route->path, '/'),
                                '/'
                            );
                        }

                        $routes[] = new Route(
                            $httpMethod,
                            $path,
                            $controller,
                            $method->getName()
                        );
                    }
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