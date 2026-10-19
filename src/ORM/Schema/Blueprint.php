<?php

namespace Magma\ORM\Schema;

use Magma\ORM\Database;
use InvalidArgumentException;
use LogicException;
use PDO;

final class Blueprint
{
    private const FK_ACTIONS = ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'];

    /** @var ColumnDefinition[] */
    private array $columns = [];
    private array $indexes = [];
    private array $drops = [];
    private string $driver;

    public function __construct(private string $table, private bool $creating)
    {
        $this->driver = Database::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /* ---------- Column types ---------- */

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->add('id', $name);
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->add('string', $name, ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->add('text', $name);
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->add('integer', $name);
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->add('bigInteger', $name);
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->add('boolean', $name);
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->add('decimal', $name, ['precision' => $precision, 'scale' => $scale]);
    }

    public function date(string $name): ColumnDefinition
    {
        return $this->add('date', $name);
    }

    public function dateTime(string $name): ColumnDefinition
    {
        return $this->add('dateTime', $name);
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->add('timestamp', $name);
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->add('json', $name);
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->add('foreignId', $name);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    public function softDeletes(): ColumnDefinition
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    /* ---------- Indexes / drops ---------- */

    public function unique(array|string $columns): void
    {
        $this->indexes[] = ['unique', (array) $columns];
    }

    public function index(array|string $columns): void
    {
        $this->indexes[] = ['index', (array) $columns];
    }

    public function dropColumn(string ...$names): void
    {
        array_push($this->drops, ...$names);
    }

    /* ---------- Compilation ---------- */

    /** @return string[] SQL statements to run in order */
    public function toSql(): array
    {
        $table = Schema::quote($this->table);
        $sql = [];

        if ($this->creating) {
            if (!$this->columns) {
                throw new LogicException("Table {$this->table} has no columns.");
            }
            $defs = array_map(fn(ColumnDefinition $c) => $this->columnSql($c->attributes), $this->columns);
            foreach ($this->columns as $c) {
                if (isset($c->attributes['fk'])) {
                    $defs[] = $this->foreignSql($c->attributes['name'], $c->attributes['fk']);
                }
            }
            $sql[] = "CREATE TABLE $table (" . implode(', ', $defs) . ')';
        } else {
            foreach ($this->columns as $c) {
                $sql[] = "ALTER TABLE $table ADD COLUMN " . $this->columnSql($c->attributes);
                if (isset($c->attributes['fk'])) {
                    if ($this->driver === 'sqlite') {
                        throw new LogicException('SQLite cannot add a foreign key to an existing table.');
                    }
                    $sql[] = "ALTER TABLE $table ADD " . $this->foreignSql($c->attributes['name'], $c->attributes['fk']);
                }
            }
            foreach ($this->drops as $name) {
                $sql[] = "ALTER TABLE $table DROP COLUMN " . Schema::quote($name);
            }
        }

        $indexes = $this->indexes;
        foreach ($this->columns as $c) {
            if (!empty($c->attributes['unique'])) {
                $indexes[] = ['unique', [$c->attributes['name']]];
            }
            if (!empty($c->attributes['index'])) {
                $indexes[] = ['index', [$c->attributes['name']]];
            }
        }
        foreach ($indexes as [$type, $cols]) {
            $name = $this->indexName($type, $cols);
            $colList = implode(', ', array_map(Schema::quote(...), $cols));
            $sql[] = 'CREATE ' . ($type === 'unique' ? 'UNIQUE ' : '') . "INDEX $name ON $table ($colList)";
        }

        return $sql;
    }

    private function add(string $type, string $name, array $extra = []): ColumnDefinition
    {
        return $this->columns[] = new ColumnDefinition(['type' => $type, 'name' => $name, 'nullable' => false] + $extra);
    }

    private function columnSql(array $a): string
    {
        $name = Schema::quote($a['name']);

        if ($a['type'] === 'id') {
            return $name . ' ' . match ($this->driver) {
                'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'pgsql'  => 'BIGSERIAL PRIMARY KEY',
                default  => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            };
        }

        $sql = $name . ' ' . $this->typeSql($a) . ($a['nullable'] ? ' NULL' : ' NOT NULL');

        if (isset($a['defaultRaw'])) {
            $sql .= ' DEFAULT ' . $a['defaultRaw'];
        } elseif (!empty($a['hasDefault'])) {
            $sql .= ' DEFAULT ' . $this->literal($a['default']);
        }
        return $sql;
    }

    private function typeSql(array $a): string
    {
        $d = $this->driver;
        return match ($a['type']) {
            'string'     => 'VARCHAR(' . (int) $a['length'] . ')',
            'text'       => 'TEXT',
            'integer'    => $d === 'sqlite' ? 'INTEGER' : 'INT',
            'bigInteger' => $d === 'sqlite' ? 'INTEGER' : 'BIGINT',
            'boolean'    => match ($d) { 'mysql' => 'TINYINT(1)', 'pgsql' => 'BOOLEAN', default => 'INTEGER' },
            'decimal'    => 'DECIMAL(' . (int) $a['precision'] . ', ' . (int) $a['scale'] . ')',
            'date'       => 'DATE',
            'dateTime'   => $d === 'pgsql' ? 'TIMESTAMP' : 'DATETIME',
            'timestamp'  => 'TIMESTAMP',
            'json'       => match ($d) { 'mysql' => 'JSON', 'pgsql' => 'JSONB', default => 'TEXT' },
            'foreignId'  => match ($d) { 'mysql' => 'BIGINT UNSIGNED', 'pgsql' => 'BIGINT', default => 'INTEGER' },
            default      => throw new InvalidArgumentException("Unknown column type: {$a['type']}"),
        };
    }

    private function literal(mixed $value): string
    {
        return match (true) {
            $value === null  => 'NULL',
            is_bool($value)  => $this->driver === 'pgsql' ? ($value ? 'TRUE' : 'FALSE') : ($value ? '1' : '0'),
            is_int($value), is_float($value) => (string) $value,
            default          => Database::pdo()->quote((string) $value),
        };
    }

    private function foreignSql(string $column, array $fk): string
    {
        [$refTable, $refColumn, $onDelete] = $fk;
        if (!in_array($onDelete, self::FK_ACTIONS, true)) {
            throw new InvalidArgumentException("Invalid ON DELETE action: $onDelete");
        }
        return 'FOREIGN KEY (' . Schema::quote($column) . ') REFERENCES '
            . Schema::quote($refTable) . ' (' . Schema::quote($refColumn) . ") ON DELETE $onDelete";
    }

    private function indexName(string $type, array $cols): string
    {
        $name = $this->table . '_' . implode('_', $cols) . '_' . $type;
        if (strlen($name) > 60) {
            $name = substr($name, 0, 53) . '_' . substr(md5($name), 0, 6);
        }
        return Schema::quote($name);
    }
}