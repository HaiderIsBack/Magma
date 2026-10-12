<?php

namespace Magma\Core;

final class Application {
    public static function run() {
        $router = new \Magma\Routing\Router();

        $cache = new \Magma\Routing\RouteCache(
            dirname(APP_ROOT) . '/storage/cache/routes.php'
        );

        $routes = $cache->load();

        if ($routes === null) {
            $discovery = new \Magma\Routing\ControllerDiscovery(
                dirname(APP_ROOT) . '/app/Controllers',
                'App\\Controllers'
            );

            $controllers = $discovery->discover();

            $routeLoader = new \Magma\Routing\RouteLoader();

            $definitions = $routeLoader->load($controllers);

            $cache->save($definitions);

            $routes = $definitions;
        } else {
            $routes = array_map(
                static fn (array $route): \Magma\Routing\Route => new \Magma\Routing\Route(
                    $route['method'],
                    $route['path'],
                    $route['controller'],
                    $route['action']
                ),
                $routes
            );
        }

        foreach ($routes as $route) {
            $router->addRoute($route);
        }

        return $router;
    }
}