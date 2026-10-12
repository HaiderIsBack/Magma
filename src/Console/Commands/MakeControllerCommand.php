<?php

namespace Magma\Console\Commands;

final class MakeControllerCommand
{
    public function execute(array $arguments): int
    {
        $name = $arguments[0] ?? null;

        if ($name === null || $name === '') {
            fwrite(STDERR, "Usage: php magma make:controller <name>\n");

            return 1;
        }

        // Allow names such as UserController or Admin/UserController.
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', trim($name, '/'));

        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                fwrite(STDERR, "Invalid controller name: {$name}\n");

                return 1;
            }
        }

        $className = array_pop($parts);

        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        $namespaceParts = array_merge(['App', 'Controllers'], $parts);
        $namespace = implode('\\', $namespaceParts);

        $directory = APP_ROOT
            . '/app/Controllers'
            . ($parts !== [] ? '/' . implode('/', $parts) : '');

        if (!is_dir($directory) &&
            !mkdir($directory, 0755, true) &&
            !is_dir($directory)) {
            fwrite(STDERR, "Could not create directory: {$directory}\n");

            return 1;
        }

        $file = $directory . '/' . $className . '.php';

        if (file_exists($file)) {
            fwrite(STDERR, "Controller already exists: {$file}\n");

            return 1;
        }

        $contents = <<<PHP
        <?php

        namespace {$namespace};

        use Magma\\Core\\BaseController;
        use Magma\\Http\\Request;
        use Magma\\Http\\Get;

        final class {$className} extends BaseController 
        {
            #[Get('/')]
            public function index(Request \$request) {
                // TODO: Implement this action.
            }
        }

        PHP;

        if (file_put_contents($file, $contents) === false) {
            fwrite(STDERR, "Could not write controller file.\n");

            return 1;
        }

        echo "Controller created: {$file}\n";

        return 0;
    }
}