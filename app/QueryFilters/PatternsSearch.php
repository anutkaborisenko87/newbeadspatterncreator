<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;

class PatternsSearch extends MainQueryFilter
{
    public function applyFilter($builder, $request): Builder
    {
        $search = $request->query($this->filterName());
        if (!is_string($search) || trim($search) === '') {
            return $builder;
        }

        $term = '%'.strtr(trim($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $operator = $builder->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $builder->where(function (Builder $query) use ($operator, $term) {
            $query->whereRaw("title $operator ? ESCAPE '!'", [$term])
                ->orWhereRaw("description $operator ? ESCAPE '!'", [$term]);
        });
    }
}
