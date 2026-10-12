<?php

namespace Magma\Console;

use Magma\Console\Commands\MakeControllerCommand;

final class Console
{
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;
        $arguments = array_slice($argv, 2);

        return match ($command) {
            'make:controller' => (new MakeControllerCommand())->execute($arguments),
            default => $this->unknownCommand($command),
        };
    }

    private function unknownCommand(?string $command): int
    {
        if ($command === null || $command === 'help') {
            echo "Magma Framework CLI\n\n";
            echo "Available commands:\n";
            echo "  make:controller <name>  Create a controller\n";

            return 0;
        }

        fwrite(STDERR, "Unknown command: {$command}\n");
        fwrite(STDERR, "Run 'php magma help' to see available commands.\n");

        return 1;
    }
}