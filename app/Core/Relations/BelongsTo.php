<?php

namespace App\Core\Relations;

use App\Core\Model;

class BelongsTo extends Relation
{
    public function __construct(string $relatedClass, Model $parent, string $foreignKey, string $ownerKey)
    {
        parent::__construct($relatedClass, $parent);

        $this->query->where($ownerKey, $parent->getAttribute($foreignKey));
    }

    public function getResults()
    {
        return $this->first();
    }
}
