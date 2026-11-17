<?php

namespace App\QueryFilters;

class PatternsSortBy extends BasePatternsSortBy
{
    protected function sortKey(): string
    {
        return $this->filterName();
    }
}
