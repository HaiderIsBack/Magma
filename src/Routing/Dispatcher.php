<?php

namespace Magma\Routing;

final class Dispatcher
{
    public function dispatch(array $match): void
    {
        $handler = $match['handler'];
        $params = $match['params'] ?? [];

        $controllerClass = $handler['controller'];
        $action = $handler['action'];

        if (!class_exists($controllerClass)) {
            http_response_code(500);
            echo 'Controller class not found.';
            return;
        }

        $controller = new $controllerClass();

        if (!is_callable([$controller, $action])) {
            http_response_code(500);
            echo 'Controller action not found.';
            return;
        }

        $result = $controller->$action(...array_values($params));

        if (is_string($result)) {
            echo $result;
        } elseif ($result !== null) {
            http_response_code(500);
            echo 'Controller returned an unsupported response.';
        }
    }
}
