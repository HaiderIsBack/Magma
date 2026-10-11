<?php

namespace Magma\Routing;

final class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $controller,
        public readonly string $action
    ) {}
}