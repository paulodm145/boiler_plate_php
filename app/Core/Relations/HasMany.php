<?php

namespace App\Core\Relations;

use App\Core\Model;

class HasMany extends Relation
{
    public function __construct(string $relatedClass, Model $parent, string $foreignKey, string $localKey)
    {
        parent::__construct($relatedClass, $parent);

        $this->query->where($foreignKey, $parent->getAttribute($localKey));
    }

    public function getResults()
    {
        return $this->get();
    }
}
