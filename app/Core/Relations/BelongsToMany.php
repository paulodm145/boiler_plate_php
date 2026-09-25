<?php

namespace App\Core\Relations;

use App\Core\Model;
use App\Database\Database;

class BelongsToMany extends Relation
{
    private $relatedClass;
    private $pivotTable;
    private $foreignPivotKey;
    private $relatedPivotKey;

    public function __construct(string $relatedClass, Model $parent, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey)
    {
        parent::__construct($relatedClass, $parent);

        $this->relatedClass = $relatedClass;
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
    }

    public function getResults()
    {
        return $this->get();
    }

    public function get(): array
    {
        $ids = $this->relatedIds();

        if (empty($ids)) {
            return [];
        }

        $relatedClass = $this->relatedClass;
        $ownerKey = (new $relatedClass())->getKeyName();

        return $relatedClass::query()->whereIn($ownerKey, $ids)->get();
    }

    public function attach(int $relatedId): void
    {
        $parentId = $this->parentId();

        $existing = $this->connection()->fetch(
            'SELECT ' . $this->foreignPivotKey . ' FROM ' . $this->pivotTable
                . ' WHERE ' . $this->foreignPivotKey . ' = :parent_id AND ' . $this->relatedPivotKey . ' = :related_id',
            ['parent_id' => $parentId, 'related_id' => $relatedId]
        );

        if ($existing !== null) {
            return;
        }

        $this->connection()->execute(
            'INSERT INTO ' . $this->pivotTable . ' (' . $this->foreignPivotKey . ', ' . $this->relatedPivotKey . ')'
                . ' VALUES (:parent_id, :related_id)',
            ['parent_id' => $parentId, 'related_id' => $relatedId]
        );
    }

    public function detach(int $relatedId): void
    {
        $parentId = $this->parentId();

        $this->connection()->execute(
            'DELETE FROM ' . $this->pivotTable
                . ' WHERE ' . $this->foreignPivotKey . ' = :parent_id AND ' . $this->relatedPivotKey . ' = :related_id',
            ['parent_id' => $parentId, 'related_id' => $relatedId]
        );
    }

    public function sync(array $relatedIds): void
    {
        foreach ($this->relatedIds() as $currentId) {
            if (!in_array($currentId, $relatedIds, true)) {
                $this->detach($currentId);
            }
        }

        foreach ($relatedIds as $relatedId) {
            $this->attach($relatedId);
        }
    }

    private function relatedIds(): array
    {
        $rows = $this->connection()->fetchAll(
            'SELECT ' . $this->relatedPivotKey . ' FROM ' . $this->pivotTable . ' WHERE ' . $this->foreignPivotKey . ' = :parent_id',
            ['parent_id' => $this->parentId()]
        );

        $column = $this->relatedPivotKey;

        return array_map(function (array $row) use ($column): int {
            return (int) $row[$column];
        }, $rows);
    }

    private function parentId(): int
    {
        return (int) $this->parent->getAttribute($this->parent->getKeyName());
    }

    private function connection(): Database
    {
        $parentClass = get_class($this->parent);

        return $parentClass::connection();
    }
}
