<?php

namespace Magma\ORM;

use InvalidArgumentException;
use PDOStatement;

use Magma\ORM\Database;

class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    /** @param class-string<Model>|null $modelClass */
    public function __construct(private string $table, private ?string $modelClass = null) {}

    /* ---------- Building ---------- */

    public function select(string ...$columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }
        $operator = strtoupper((string) $operator);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid operator: $operator");
        }

        if ($value === null) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $this->wheres[] = [$boolean, $this->q($column) . " $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        return func_num_args() === 2
            ? $this->where($column, '=', $operator, 'OR')
            : $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): static
    {
        if ($values === []) {
            $this->wheres[] = [$boolean, '0 = 1'];
            return $this;
        }
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [$boolean, $this->q($column) . " IN ($marks)"];
        array_push($this->bindings, ...array_values($values));
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [$boolean, $this->q($column) . ($not ? ' IS NOT NULL' : ' IS NULL')];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        return $this->whereNull($column, 'AND', true);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true) || !in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException('Invalid join definition.');
        }
        $this->joins[] = "$type JOIN " . $this->q($table) . ' ON ' . $this->q($first) . " $operator " . $this->q($second);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->q($column) . " $direction";
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): static
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->limit($perPage)->offset(max(0, $page - 1) * $perPage);
    }

    /* ---------- Reading ---------- */

    /** @return array<int, Model|array> */
    public function get(): array
    {
        $rows = $this->run($this->toSql(), $this->bindings)->fetchAll();
        if ($this->modelClass === null) {
            return $rows;
        }
        return array_map(fn(array $row) => ($this->modelClass)::hydrate($row), $rows);
    }

    public function first(): Model|array|null
    {
        return (clone $this)->limit(1)->get()[0] ?? null;
    }

    public function count(): int
    {
        $copy = clone $this;
        $copy->columns = ['*'];
        $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $this->q($this->table) . $copy->compileClauses(false);
        return (int) $this->run($sql, $this->bindings)->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function pluck(string $column): array
    {
        $copy = clone $this;
        $copy->columns = [$column];
        $copy->modelClass = null;
        $key = str_contains($column, '.') ? substr(strrchr($column, '.'), 1) : $column;
        return array_column($copy->get(), $key);
    }

    public function toSql(): string
    {
        $cols = implode(', ', array_map(fn($c) => $this->q($c), $this->columns));
        return "SELECT $cols FROM " . $this->q($this->table) . $this->compileClauses();
    }

    /* ---------- Writing ---------- */

    public function insert(array $data): string|false
    {
        if ($data === []) {
            throw new InvalidArgumentException('Nothing to insert.');
        }
        $cols  = implode(', ', array_map(fn($c) => $this->q($c), array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $this->run('INSERT INTO ' . $this->q($this->table) . " ($cols) VALUES ($marks)", array_values($data));
        return Database::pdo()->lastInsertId();
    }

    public function update(array $data): int
    {
        $this->guardAgainstUnscoped('update');
        $set = implode(', ', array_map(fn($c) => $this->q($c) . ' = ?', array_keys($data)));
        $sql = 'UPDATE ' . $this->q($this->table) . " SET $set" . $this->compileWheres();
        return $this->run($sql, [...array_values($data), ...$this->bindings])->rowCount();
    }

    public function delete(): int
    {
        $this->guardAgainstUnscoped('delete');
        $sql = 'DELETE FROM ' . $this->q($this->table) . $this->compileWheres();
        return $this->run($sql, $this->bindings)->rowCount();
    }

    /* ---------- Internals ---------- */

    private function compileClauses(bool $withOrderAndLimit = true): string
    {
        $sql = $this->joins ? ' ' . implode(' ', $this->joins) : '';
        $sql .= $this->compileWheres();
        if ($withOrderAndLimit) {
            if ($this->orders) {
                $sql .= ' ORDER BY ' . implode(', ', $this->orders);
            }
            if ($this->limit !== null) {
                $sql .= ' LIMIT ' . $this->limit;
            }
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        return $sql;
    }

    private function compileWheres(): string
    {
        if (!$this->wheres) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $clause]) {
            $sql .= ($i === 0 ? '' : " $boolean ") . $clause;
        }
        return ' WHERE ' . $sql;
    }

    private function guardAgainstUnscoped(string $action): void
    {
        if (!$this->wheres) {
            throw new \LogicException("Refusing to $action without a WHERE clause.");
        }
    }

    private function run(string $sql, array $bindings): PDOStatement
    {
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    /** Validate and quote an identifier like `users.id`. */
    private function q(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.([A-Za-z_][A-Za-z0-9_]*|\*))?$|^\*$/', $identifier)) {
            throw new InvalidArgumentException("Invalid identifier: $identifier");
        }
        $quote = Database::quoteChar();
        return implode('.', array_map(
            fn($p) => $p === '*' ? '*' : $quote . $p . $quote,
            explode('.', $identifier)
        ));
    }
}