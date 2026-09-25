<?php

namespace App\Core;

use App\Database\Database;
use InvalidArgumentException;

class QueryBuilder
{
    private static $operators = ['=', '!=', '>', '>=', '<', '<=', 'LIKE'];

    private $modelClass;
    private $table;
    private $wheres = [];
    private $bindings = [];
    private $paramCounter = 0;
    private $orderByColumn;
    private $orderByDirection = 'asc';
    private $limitValue;
    private $offsetValue;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
        $this->table = (new $modelClass())->getTable();
    }

    // $column (aqui e em orderBy()/whereIn()) e concatenado direto na SQL,
    // sem allowlist — so o $operator passa por validacao. Isso espelha o
    // resto do boilerplate: nomes de coluna sao sempre literais escritos no
    // codigo (nunca vindos de input do usuario), o mesmo modelo de confianca
    // que Database::fetch()/execute() ja usam em toda a camada de
    // Repository. Nunca construa um $column a partir de input externo.
    public function where(string $column, $operatorOrValue, $value = null): self
    {
        if (func_num_args() === 2) {
            $operator = '=';
            $boundValue = $operatorOrValue;
        } else {
            $operator = $operatorOrValue;
            $boundValue = $value;
        }

        if (!in_array($operator, self::$operators, true)) {
            throw new InvalidArgumentException('Operador nao suportado: ' . $operator);
        }

        $param = 'p' . $this->paramCounter;
        $this->paramCounter++;

        $this->wheres[] = $column . ' ' . $operator . ' :' . $param;
        $this->bindings[$param] = $boundValue;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = '1 = 0';

            return $this;
        }

        $prefix = 'p' . $this->paramCounter;
        $this->paramCounter++;
        $placeholders = [];

        foreach (array_values($values) as $index => $value) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $this->bindings[$name] = $value;
        }

        $this->wheres[] = $column . ' IN (' . implode(', ', $placeholders) . ')';

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderByColumn = $column;
        $this->orderByDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitValue = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;

        return $this;
    }

    public function get(): array
    {
        $rows = $this->connection()->fetchAll($this->toSql(), $this->bindings);
        $modelClass = $this->modelClass;
        $models = [];

        foreach ($rows as $row) {
            $models[] = $modelClass::hydrate($row);
        }

        return $models;
    }

    public function first(): ?Model
    {
        $originalLimit = $this->limitValue;
        $this->limitValue = 1;
        $results = $this->get();
        $this->limitValue = $originalLimit;

        return count($results) > 0 ? $results[0] : null;
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $this->table;

        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        $row = $this->connection()->fetch($sql, $this->bindings);

        return $row === null ? 0 : (int) $row['aggregate'];
    }

    private function toSql(): string
    {
        $sql = 'SELECT * FROM ' . $this->table;

        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orderByColumn !== null) {
            $sql .= ' ORDER BY ' . $this->orderByColumn . ' ' . $this->orderByDirection;
        }

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . (int) $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . (int) $this->offsetValue;
        }

        return $sql;
    }

    private function connection(): Database
    {
        $modelClass = $this->modelClass;

        return $modelClass::connection();
    }
}
