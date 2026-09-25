<?php

namespace App\Core\Relations;

use App\Core\Model;

abstract class Relation
{
    protected $query;
    protected $parent;

    public function __construct(string $relatedClass, Model $parent)
    {
        $this->query = $relatedClass::query();
        $this->parent = $parent;
    }

    abstract public function getResults();

    public function where(string $column, $operatorOrValue, $value = null): self
    {
        if (func_num_args() === 2) {
            $this->query->where($column, $operatorOrValue);
        } else {
            $this->query->where($column, $operatorOrValue, $value);
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    public function get(): array
    {
        return $this->query->get();
    }

    public function first(): ?Model
    {
        return $this->query->first();
    }
}
