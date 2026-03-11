<?php

namespace App\Http\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Filter
{
    protected Request $request;

    protected Builder $builder;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply the filters on the builder based on request parameters.
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->request->all() as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (method_exists($this, $name)) {
                call_user_func_array([$this, $name], array_filter([$value]));
            }
        }

        return $this->builder;
    }
}
