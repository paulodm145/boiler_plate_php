<?php

namespace App\Core\Relations;

class HasOne extends HasMany
{
    public function getResults()
    {
        return $this->first();
    }
}
