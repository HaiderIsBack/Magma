<?php

namespace Magma\Routing;

use Magma\Http\Request;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Dispatcher
{
    public function dispatch(array $match, Request $request): void
    {
        $route = $match['handler'];
        $routeParams = $match['params'] ?? [];

        $controllerClass = $route['controller'];
        $action = $route['action'];

        if (!class_exists($controllerClass)) {
            throw new RuntimeException(
                "Controller not found: {$controllerClass}"
            );
        }

        $controller = new $controllerClass();

        if (!is_callable([$controller, $action])) {
            throw new RuntimeException(
                "Controller action not found: {$controllerClass}::{$action}"
            );
        }

        $method = new ReflectionMethod($controller, $action);
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (
                $type instanceof ReflectionNamedType
                && !$type->isBuiltin()
                && $type->getName() === Request::class
            ) {
                $arguments[] = $request;
                continue;
            }

            $name = $parameter->getName();

            if (array_key_exists($name, $routeParams)) {
                $arguments[] = $routeParams[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException(
                "Cannot resolve controller parameter: {$name}"
            );
        }

        $result = $method->invokeArgs($controller, $arguments);

        if (is_array($result)) {
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode(
                $result,
                JSON_THROW_ON_ERROR
            );

            return;
        }

        if (is_string($result)) {
            echo $result;
        }
    }
}
