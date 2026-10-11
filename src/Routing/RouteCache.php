<?php

namespace Magma\Routing;

use Magma\Routing\Route;
use RuntimeException;

final class RouteCache
{
    public function __construct(
        private string $cacheFile
    ) {}

    public function load(): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }

        $routes = require $this->cacheFile;

        if (!is_array($routes)) {
            throw new RuntimeException(
                'Invalid route cache format.'
            );
        }

        return $routes;
    }

    public function save(array $routes): void
    {
        $directory = dirname($this->cacheFile);

        if (!is_dir($directory) && !mkdir($directory, 0750, true)) {
            throw new RuntimeException(
                'Unable to create route cache directory.'
            );
        }

        $data = array_map(
            static fn (Route $route): array => [
                'method' => $route->method,
                'path' => $route->path,
                'controller' => $route->controller,
                'action' => $route->action,
            ],
            $routes
        );

        $content = "<?php\n\nreturn "
            . var_export($data, true)
            . ";\n";

        $temporaryFile = tempnam($directory, 'routes_');

        if ($temporaryFile === false) {
            throw new RuntimeException(
                'Unable to create temporary route cache.'
            );
        }

        try {
            if (file_put_contents($temporaryFile, $content, LOCK_EX) === false) {
                throw new RuntimeException(
                    'Unable to write route cache.'
                );
            }

            // Rename on the same filesystem for atomic replacement.
            if (!rename($temporaryFile, $this->cacheFile)) {
                throw new RuntimeException(
                    'Unable to replace route cache.'
                );
            }

            $temporaryFile = '';
        } finally {
            if ($temporaryFile !== '' && is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}