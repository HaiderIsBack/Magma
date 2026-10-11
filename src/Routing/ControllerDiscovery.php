<?php

namespace Magma\Routing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use ReflectionClass;
use Magma\Core\BaseController;
use RuntimeException;

final class ControllerDiscovery
{
    public function __construct(
        private string $directory,
        private string $namespace
    ) {}

    /**
     * @return list<class-string>
     */
    public function discover(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException(
                "Controller directory not found."
            );
        }

        $controllers = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->directory,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr(
                $file->getPathname(),
                strlen(rtrim($this->directory, DIRECTORY_SEPARATOR))
                    + 1
            );

            $classPath = substr($relativePath, 0, -4);

            $class = rtrim($this->namespace, '\\')
                . '\\'
                . str_replace(
                    DIRECTORY_SEPARATOR,
                    '\\',
                    $classPath
                );

            if (!class_exists($class)) {
                throw new RuntimeException(
                    "Controller class could not be loaded: {$class}"
                );
            }

            $reflection = new ReflectionClass($class);

            if (
                !$reflection->isInstantiable()
                || $reflection->isAbstract()
                || !$reflection->isSubclassOf(BaseController::class)
            ) {
                continue;
            }

            $controllers[] = $class;
        }

        sort($controllers);

        return array_values(array_unique($controllers));
    }
}
