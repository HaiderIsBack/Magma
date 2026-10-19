<?php

namespace Magma\ORM;

use Magma\ORM\Relation;
use Magma\ORM\QueryBuilder;

abstract class Model {
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];   // empty = nothing mass-assignable
    protected static array $hidden = [];     // excluded from toArray()/JSON
    protected static bool $timestamps = true;
    protected static bool $softDeletes = false;

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];
    protected bool $exists = false;
 
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function table(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }
        $short = (new \ReflectionClass(static::class))->getShortName();
        return self::snake($short) . 's';
    }

    public static function query(): QueryBuilder
    {
        $q = new QueryBuilder(static::table(), static::class);
        if (static::$softDeletes) {
            $q->whereNull(static::table() . '.deleted_at');
        }
        return $q;
    }

    public static function withTrashed(): QueryBuilder
    {
        return new QueryBuilder(static::table(), static::class);
    }
 
    public static function __callStatic(string $method, array $args): mixed
    {
        return static::query()->$method(...$args);
    }
 
    public static function all(): array
    {
        return static::query()->get();
    }
 
    public static function find(int|string $id): ?static
    {
        return static::query()->where(static::table() . '.' . static::$primaryKey, $id)->first();
    }
 
    public static function findOrFail(int|string $id): static
    {
        return static::find($id) ?? throw new \RuntimeException(static::class . " #$id not found.");
    }
 
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }
 
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;
        return $model;
    }
 
    /* ---------- Attributes ---------- */
 
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }
 
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
 
    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        if (method_exists($this, $key)) {
            $rel = $this->$key();
            if ($rel instanceof Relation) {
                return $this->relations[$key] = $rel->getResults();
            }
        }
        return null;
    }
 
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }
 
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
 
    public function isDirty(): bool
    {
        return $this->getDirty() !== [];
    }
 
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] != $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }
 
    /* ---------- Persistence ---------- */
 
    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');
 
        if ($this->exists) {
            $dirty = $this->getDirty();
            if (!$dirty) {
                return true;
            }
            if (static::$timestamps) {
                $this->attributes['updated_at'] = $dirty['updated_at'] = $now;
            }
            static::withTrashed()
                ->where(static::$primaryKey, $this->getAttribute(static::$primaryKey))
                ->update($dirty);
        } else {
            if (static::$timestamps) {
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] ??= $now;
            }
            $id = (new QueryBuilder(static::table()))->insert($this->attributes);
            if (!isset($this->attributes[static::$primaryKey]) && $id !== false) {
                $this->attributes[static::$primaryKey] = ctype_digit((string) $id) ? (int) $id : $id;
            }
            $this->exists = true;
        }
 
        $this->original = $this->attributes;
        return true;
    }
 
    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }
 
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        $pk = $this->getAttribute(static::$primaryKey);
 
        if (static::$softDeletes) {
            $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
            return $this->save();
        }
 
        static::withTrashed()->where(static::$primaryKey, $pk)->delete();
        $this->exists = false;
        return true;
    }
 
    public function forceDelete(): bool
    {
        static::withTrashed()->where(static::$primaryKey, $this->getAttribute(static::$primaryKey))->delete();
        $this->exists = false;
        return true;
    }
 
    public function restore(): bool
    {
        $this->attributes['deleted_at'] = null;
        return $this->save();
    }
 
    public function refresh(): static
    {
        $fresh = static::withTrashed()->where(static::$primaryKey, $this->getAttribute(static::$primaryKey))->first();
        if ($fresh) {
            $this->attributes = $this->original = $fresh->attributes;
            $this->relations = [];
        }
        return $this;
    }
 
    /* ---------- Relations ---------- */
 
    protected function hasMany(string $related, ?string $foreignKey = null, string $localKey = 'id'): Relation
    {
        $fk = $foreignKey ?? self::snake($this->shortName()) . '_id';
        return new Relation($related::query()->where($fk, $this->getAttribute($localKey)), true);
    }
 
    protected function hasOne(string $related, ?string $foreignKey = null, string $localKey = 'id'): Relation
    {
        $fk = $foreignKey ?? self::snake($this->shortName()) . '_id';
        return new Relation($related::query()->where($fk, $this->getAttribute($localKey)), false);
    }
 
    protected function belongsTo(string $related, ?string $foreignKey = null, string $ownerKey = 'id'): Relation
    {
        $short = (new \ReflectionClass($related))->getShortName();
        $fk = $foreignKey ?? self::snake($short) . '_id';
        return new Relation($related::query()->where($ownerKey, $this->getAttribute($fk)), false);
    }
 
    /* ---------- Serialization ---------- */
 
    public function toArray(): array
    {
        $out = array_diff_key($this->attributes, array_flip(static::$hidden));
        foreach ($this->relations as $name => $value) {
            $out[$name] = is_array($value)
                ? array_map(fn($m) => $m instanceof self ? $m->toArray() : $m, $value)
                : ($value instanceof self ? $value->toArray() : $value);
        }
        return $out;
    }
 
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
 
    private function shortName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
 
    private static function snake(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}