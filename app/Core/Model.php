<?php

namespace App\Core;

use App\Database\Database;
use DateTime;
use DateTimeZone;
use ReflectionMethod;
use RuntimeException;

abstract class Model
{
    protected $table;
    // Sempre uma coluna inteira auto-increment — save() casta o valor lido
    // de lastInsertId() pra (int). Um identificador publico nao-inteiro
    // (uuid) fica em $uuidColumn, coexistindo com esta PK, nunca a
    // substituindo.
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $casts = [];
    protected $timestamps = true;
    protected $uuidColumn;

    private $attributes = [];
    private $relations = [];
    private $exists = false;

    private static $connection;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function setConnection(Database $database): void
    {
        self::$connection = $database;
    }

    public static function connection(): Database
    {
        if (self::$connection === null) {
            self::$connection = new Database();
        }

        return self::$connection;
    }

    public static function hydrate(array $row): self
    {
        $instance = new static();
        $instance->attributes = $row;
        $instance->exists = true;

        return $instance;
    }

    public static function create(array $attributes): self
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::class);
    }

    public static function find(int $id): ?self
    {
        $instance = new static();

        return static::query()->where($instance->getKeyName(), $id)->first();
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function findByUuid(string $uuid): ?self
    {
        $instance = new static();

        if ($instance->uuidColumn === null) {
            throw new RuntimeException(static::class . ' nao declarou $uuidColumn.');
        }

        return static::query()->where($instance->uuidColumn, $uuid)->first();
    }

    public function getTable(): string
    {
        if ($this->table === null) {
            throw new RuntimeException(static::class . ' precisa declarar $table.');
        }

        return $this->table;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    public function getAttribute(string $name)
    {
        if (!array_key_exists($name, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$name];

        if ($value === null || !array_key_exists($name, $this->casts)) {
            return $value;
        }

        switch ($this->casts[$name]) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'array':
                $decoded = json_decode((string) $value, true);
                return $decoded === null ? [] : $decoded;
            case 'datetime':
                return $value === '' ? null : new DateTime($value, new DateTimeZone('UTC'));
            default:
                return $value;
        }
    }

    public function setAttribute(string $name, $value): void
    {
        if (array_key_exists($name, $this->casts)) {
            switch ($this->casts[$name]) {
                case 'int':
                    $value = (int) $value;
                    break;
                case 'float':
                    $value = (float) $value;
                    break;
                case 'bool':
                    $value = $value ? 1 : 0;
                    break;
                case 'array':
                    $value = json_encode($value);
                    break;
                case 'datetime':
                    if ($value instanceof DateTime) {
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    break;
            }
        }

        $this->attributes[$name] = $value;
    }

    public function toArray(): array
    {
        $result = [];

        foreach (array_keys($this->attributes) as $key) {
            $value = $this->getAttribute($key);
            $result[$key] = $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value;
        }

        return $result;
    }

    public function save(): self
    {
        if ($this->timestamps) {
            $now = gmdate('Y-m-d H:i:s');

            if (!$this->exists) {
                $this->attributes['created_at'] = $now;
            }

            $this->attributes['updated_at'] = $now;
        }

        if ($this->uuidColumn !== null && empty($this->attributes[$this->uuidColumn])) {
            $this->attributes[$this->uuidColumn] = Uuid::v4();
        }

        if ($this->exists) {
            $this->performUpdate();
        } else {
            $this->performInsert();
        }

        return $this;
    }

    public function delete(): void
    {
        if (!$this->exists || !array_key_exists($this->primaryKey, $this->attributes)) {
            throw new RuntimeException('Nao e possivel deletar um model que nao existe no banco.');
        }

        static::connection()->execute(
            'DELETE FROM ' . $this->getTable() . ' WHERE ' . $this->primaryKey . ' = :' . $this->primaryKey,
            [$this->primaryKey => $this->attributes[$this->primaryKey]]
        );

        $this->exists = false;
    }

    protected function hasMany(string $related, string $foreignKey, ?string $localKey = null): Relations\HasMany
    {
        $localKey = $localKey ?: $this->getKeyName();

        return new Relations\HasMany($related, $this, $foreignKey, $localKey);
    }

    protected function belongsTo(string $related, string $foreignKey, ?string $ownerKey = null): Relations\BelongsTo
    {
        $ownerKey = $ownerKey ?: (new $related())->getKeyName();

        return new Relations\BelongsTo($related, $this, $foreignKey, $ownerKey);
    }

    protected function hasOne(string $related, string $foreignKey, ?string $localKey = null): Relations\HasOne
    {
        $localKey = $localKey ?: $this->getKeyName();

        return new Relations\HasOne($related, $this, $foreignKey, $localKey);
    }

    protected function belongsToMany(string $related, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey): Relations\BelongsToMany
    {
        return new Relations\BelongsToMany($related, $this, $pivotTable, $foreignPivotKey, $relatedPivotKey);
    }

    private function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable, true);
        }

        return !in_array('*', $this->guarded, true) && !in_array($key, $this->guarded, true);
    }

    private function performInsert(): void
    {
        $columns = array_keys($this->attributes);
        $placeholders = array_map(function (string $column): string {
            return ':' . $column;
        }, $columns);

        $sql = 'INSERT INTO ' . $this->getTable()
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        static::connection()->execute($sql, $this->attributes);

        $this->attributes[$this->primaryKey] = (int) static::connection()->lastInsertId();
        $this->exists = true;
    }

    private function performUpdate(): void
    {
        $assignments = [];
        $params = [];

        foreach ($this->attributes as $column => $value) {
            if ($column === $this->primaryKey) {
                continue;
            }

            $assignments[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }

        $params[$this->primaryKey] = $this->attributes[$this->primaryKey];

        $sql = 'UPDATE ' . $this->getTable() . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $this->primaryKey . ' = :' . $this->primaryKey;

        static::connection()->execute($sql, $params);
    }

    private function isRelationMethod(string $name): bool
    {
        if (!method_exists($this, $name)) {
            return false;
        }

        $reflection = new ReflectionMethod($this, $name);

        return $reflection->getDeclaringClass()->getName() !== self::class
            && $reflection->getNumberOfParameters() === 0;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->attributes)) {
            return $this->getAttribute($name);
        }

        if (array_key_exists($name, $this->relations)) {
            return $this->relations[$name];
        }

        if ($this->isRelationMethod($name)) {
            $result = $this->$name();

            if ($result instanceof Relations\Relation) {
                $this->relations[$name] = $result->getResults();

                return $this->relations[$name];
            }
        }

        return null;
    }

    public function __set($name, $value): void
    {
        $this->setAttribute($name, $value);
    }

    public function __isset($name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public static function __callStatic($method, $args)
    {
        return call_user_func_array([static::query(), $method], $args);
    }
}
