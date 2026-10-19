<?php

namespace Magma\ORM\Migrations;

use Magma\ORM\Database;
use Magma\ORM\QueryBuilder;
use Magma\ORM\Schema\Blueprint;
use Magma\ORM\Schema\Schema;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class Migrator
{
    private const TABLE = 'migrations';

    public function __construct(private string $path)
    {
    }

    /** Run every pending migration as one batch. @return string[] names run */
    public function migrate(): array
    {
        $this->ensureTable();
        $ran = array_column($this->records(), 'migration');
        $pending = array_diff_key($this->files(), array_flip($ran));
        if (!$pending) {
            return [];
        }

        $batch = $this->lastBatch() + 1;
        foreach ($pending as $name => $file) {
            $migration = $this->resolve($file);
            $this->atomically(function () use ($migration, $name, $batch) {
                $migration->up();
                (new QueryBuilder(self::TABLE))->insert(['migration' => $name, 'batch' => $batch]);
            });
        }
        return array_keys($pending);
    }

    /** Undo the most recent batch, newest first. @return string[] names rolled back */
    public function rollback(): array
    {
        $this->ensureTable();
        $last = $this->lastBatch();
        if ($last === 0) {
            return [];
        }

        $records = array_reverse(array_filter($this->records(), fn($r) => (int) $r['batch'] === $last));
        $files = $this->files();
        $done = [];
        foreach ($records as $record) {
            $name = $record['migration'];
            if (!isset($files[$name])) {
                throw new RuntimeException("Migration file missing for $name; cannot roll back.");
            }
            $migration = $this->resolve($files[$name]);
            $this->atomically(function () use ($migration, $name) {
                $migration->down();
                (new QueryBuilder(self::TABLE))->where('migration', $name)->delete();
            });
            $done[] = $name;
        }
        return $done;
    }

    /** @return array<string, array{ran: bool, batch: ?int}> */
    public function status(): array
    {
        $this->ensureTable();
        $batches = array_column($this->records(), 'batch', 'migration');
        $out = [];
        foreach (array_keys($this->files()) as $name) {
            $out[$name] = ['ran' => isset($batches[$name]), 'batch' => isset($batches[$name]) ? (int) $batches[$name] : null];
        }
        return $out;
    }

    /** Create a timestamped migration file and return its path. */
    public function make(string $name): string
    {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException('Migration name must be snake_case, e.g. create_orders_table.');
        }
        if (!is_dir($this->path) && !mkdir($this->path, 0775, true)) {
            throw new RuntimeException("Cannot create {$this->path}");
        }

        if (preg_match('/^create_([a-z0-9_]+)_table$/', $name, $m)) {
            $up   = "Schema::create('{$m[1]}', function (Blueprint \$table) {\n            \$table->id();\n            \$table->timestamps();\n        });";
            $down = "Schema::dropIfExists('{$m[1]}');";
        } else {
            $up   = "Schema::table('table_name', function (Blueprint \$table) {\n            // \$table->string('column')->nullable();\n        });";
            $down = "Schema::table('table_name', function (Blueprint \$table) {\n            // \$table->dropColumn('column');\n        });";
        }

        $file = $this->path . '/' . date('Y_m_d_His') . '_' . $name . '.php';
        file_put_contents($file, <<<PHP
<?php
declare(strict_types=1);

use Core\ORM\Migrations\Migration;
use Core\ORM\Schema\Blueprint;
use Core\ORM\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        $up
    }

    public function down(): void
    {
        $down
    }
};

PHP);
        return $file;
    }

    /* ---------- Internals ---------- */

    private function ensureTable(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $t) {
                $t->id();
                $t->string('migration');
                $t->integer('batch');
            });
        }
    }

    private function records(): array
    {
        return (new QueryBuilder(self::TABLE))->orderBy('id')->get();
    }

    private function lastBatch(): int
    {
        $batches = array_column($this->records(), 'batch');
        return $batches ? (int) max($batches) : 0;
    }

    /** @return array<string, string> name => file path, sorted */
    private function files(): array
    {
        $files = glob($this->path . '/*.php') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $file) {
            $out[basename($file, '.php')] = $file;
        }
        return $out;
    }

    private function resolve(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new RuntimeException(basename($file) . ' must return an instance of Migration.');
        }
        return $migration;
    }

    /** MySQL auto-commits DDL, so a transaction can't protect it; SQLite/PostgreSQL can. */
    private function atomically(callable $work): void
    {
        if (Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $work();
        } else {
            Database::transaction($work);
        }
    }
}