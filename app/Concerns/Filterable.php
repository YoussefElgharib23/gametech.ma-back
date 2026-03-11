<?php

namespace App\Concerns;

use App\Http\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply all relevant filters from the request.
     */
    public function scopeFilter(Builder $query, Filter $filter): Builder
    {
        return $filter->apply($query);
    }
}
