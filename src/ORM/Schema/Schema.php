<?php

namespace Magma\ORM\Schema;

use Magma\ORM\Database;
use InvalidArgumentException;
use PDO;

final class Schema
{
    /** @param callable(Blueprint):void $callback */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, true);
        $callback($blueprint);
        self::execute($blueprint);
    }

    /** Alter an existing table: add columns, add indexes, drop columns. */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, false);
        $callback($blueprint);
        self::execute($blueprint);
    }

    public static function drop(string $table): void
    {
        Database::pdo()->exec('DROP TABLE ' . self::quote($table));
    }

    public static function dropIfExists(string $table): void
    {
        Database::pdo()->exec('DROP TABLE IF EXISTS ' . self::quote($table));
    }

    public static function hasTable(string $table): bool
    {
        $pdo = Database::pdo();
        $sql = match ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'sqlite' => "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
            'pgsql'  => 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
            default  => 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        };
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Validate and quote a single identifier (table, column or index name). */
    public static function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Invalid identifier: $identifier");
        }
        $q = Database::quoteChar();
        return $q . $identifier . $q;
    }

    private static function execute(Blueprint $blueprint): void
    {
        foreach ($blueprint->toSql() as $sql) {
            Database::pdo()->exec($sql);
        }
    }
}