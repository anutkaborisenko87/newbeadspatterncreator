<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class PatternCategoryFilter extends MainQueryFilter
{
    public function applyFilter($builder, $request): Builder
    {
        $encodedCategoryId = $request->get($this->filterName());
        $categoryId = decodeModelId($encodedCategoryId);
        if ($categoryId) {
            $builder = $builder->whereHas('category', function ($query) use ($categoryId) {
                $query->where('pattern_category_id', $categoryId);
            });
        }

        return $builder;
    }
}
