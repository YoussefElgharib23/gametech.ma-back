<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CategoryGroup extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'icon',
        'status',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'category_id' => 'integer',
        'position' => 'integer',
        'status' => 'string',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_group_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CategoryGroup $group): void {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name);
            }

            $base = $group->slug;
            $suffix = 2;
            while (static::query()
                ->where('category_id', $group->category_id)
                ->where('slug', $group->slug)
                ->exists()) {
                $group->slug = $base.'-'.$suffix;
                $suffix++;
            }
        });
    }
}
