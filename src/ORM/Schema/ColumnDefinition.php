<?php

namespace Magma\ORM\Schema;

final class ColumnDefinition
{
    public function __construct(public array $attributes) {}

    public function nullable(bool $value = true): static
    {
        $this->attributes['nullable'] = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->attributes['default'] = $value;
        $this->attributes['hasDefault'] = true;
        return $this;
    }

    /** DEFAULT CURRENT_TIMESTAMP (not supported by SQLite on ALTER TABLE ADD COLUMN). */
    public function useCurrent(): static
    {
        $this->attributes['defaultRaw'] = 'CURRENT_TIMESTAMP';
        return $this;
    }

    public function unique(): static
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    public function index(): static
    {
        $this->attributes['index'] = true;
        return $this;
    }

    /** Add a foreign key. Table defaults to the plural of the column name minus "_id" (user_id → users). */
    public function constrained(?string $table = null, string $column = 'id', string $onDelete = 'RESTRICT'): static
    {
        $table ??= preg_replace('/_id$/', '', $this->attributes['name']) . 's';
        $this->attributes['fk'] = [$table, $column, strtoupper($onDelete)];
        return $this;
    }
}