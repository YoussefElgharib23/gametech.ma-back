<?php

namespace App\Http\Filters;

use App\Enums\ProductLandingSection;
use Illuminate\Database\Eloquent\Builder;

class ProductFilter extends Filter
{
    /**
     * Filter by search term (title, sku, description, brand name).
     */
    public function search(?string $value): Builder
    {
        if ($value === null || trim($value) === '') {
            return $this->builder;
        }

        $term = '%'.trim($value).'%';

        return $this->builder->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', $term)
                ->orWhere('sku', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhereHas('brand', function (Builder $b) use ($term) {
                    $b->where('name', 'like', $term);
                })
                ->orWhereHas('category', function (Builder $b) use ($term) {
                    $b->where('name', 'like', $term);
                });
        });
    }

    /**
     * Filter by status. Ignore when value is "all".
     */
    public function status(?string $value): Builder
    {
        if ($value === null || $value === 'all') {
            return $this->builder;
        }

        return $this->builder->where('status', $value);
    }

    /**
     * Filter by category_id.
     */
    public function category_id(mixed $value): Builder
    {
        $id = is_numeric($value) ? (int) $value : null;
        if ($id === null) {
            return $this->builder;
        }

        return $this->builder->where('category_id', $id);
    }

    /**
     * Products that still need catalog placement: no category, or category set but no group and no subcategory.
     */
    public function needs_catalog(mixed $value): Builder
    {
        $enabled = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($enabled !== true) {
            return $this->builder;
        }

        return $this->builder->where(function (Builder $q): void {
            $q->whereNull('category_id')
                ->orWhere(function (Builder $q2): void {
                    $q2->whereNotNull('category_id')
                        ->whereNull('category_group_id')
                        ->whereNull('subcategory_id');
                });
        });
    }

    /**
     * Filter by brand_id.
     */
    public function brand_id(mixed $value): Builder
    {
        $id = is_numeric($value) ? (int) $value : null;
        if ($id === null) {
            return $this->builder;
        }

        return $this->builder->where('brand_id', $id);
    }

    /**
     * Filter by subcategory_id.
     */
    public function subcategory_id(mixed $value): Builder
    {
        $id = is_numeric($value) ? (int) $value : null;
        if ($id === null) {
            return $this->builder;
        }

        return $this->builder->where('subcategory_id', $id);
    }

    /**
     * Filter by category_group_id.
     */
    public function category_group_id(mixed $value): Builder
    {
        $id = is_numeric($value) ? (int) $value : null;
        if ($id === null) {
            return $this->builder;
        }

        return $this->builder->where('category_group_id', $id);
    }

    /**
     * Filter by homepage landing section (selections, new-arrival, best-seller). Use "none" for products without a section.
     */
    public function section(?string $value): Builder
    {
        if ($value === null || $value === '' || $value === 'all') {
            return $this->builder;
        }

        if ($value === 'none') {
            return $this->builder->whereNull('section');
        }

        $allowed = array_map(fn (ProductLandingSection $c) => $c->value, ProductLandingSection::cases());
        if (! in_array($value, $allowed, true)) {
            return $this->builder;
        }

        return $this->builder->where('section', $value);
    }

    /**
     * Filter by is_featured.
     */
    public function is_featured(mixed $value): Builder
    {
        return $this->builder->where('is_featured', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by min_price.
     */
    public function min_price(mixed $value): Builder
    {
        $min = is_numeric($value) ? (float) $value : null;
        if ($min === null) {
            return $this->builder;
        }

        return $this->builder->where('price', '>=', $min);
    }

    /**
     * Filter by max_price.
     */
    public function max_price(mixed $value): Builder
    {
        $max = is_numeric($value) ? (float) $value : null;
        if ($max === null) {
            return $this->builder;
        }

        return $this->builder->where('price', '<=', $max);
    }
}
