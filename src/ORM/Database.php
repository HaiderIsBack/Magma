<?php

namespace Magma\ORM;

use PDO;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    /**
     * @param array{driver?:string,host?:string,port?:int,database:string,username?:string,password?:string,charset?:string} $config
     */
    public static function connect(array $config): void
    {
        $driver  = $config['driver'] ?? 'mysql';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = match ($driver) {
            'mysql'  => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1', $config['port'] ?? 3306, $config['database'], $charset),
            'pgsql'  => sprintf('pgsql:host=%s;port=%d;dbname=%s',
                $config['host'] ?? '127.0.0.1', $config['port'] ?? 5432, $config['database']),
            'sqlite' => 'sqlite:' . $config['database'],
            default  => throw new \InvalidArgumentException("Unsupported driver: $driver"),
        };

        self::$pdo = new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function setConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function pdo(): PDO
    {
        return self::$pdo ?? throw new \RuntimeException('Database not connected. Call Database::connect() first.');
    }

    public static function quoteChar(): string
    {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? '`' : '"';
    }

    /** Run a callback inside a transaction; rolls back on any exception. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}