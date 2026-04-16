<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Subcategory extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_group_id',
        'name',
        'slug',
        'status',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'category_id' => 'integer',
        'category_group_id' => 'integer',
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

    public function categoryGroup(): BelongsTo
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Boot the model and generate slug from name when creating.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Subcategory $subcategory): void {
            if (empty($subcategory->slug)) {
                $subcategory->slug = Str::slug($subcategory->name);
            }

            if ($subcategory->category_group_id) {
                $base = $subcategory->slug;
                $suffix = 2;
                while (static::query()
                    ->where('category_group_id', $subcategory->category_group_id)
                    ->where('slug', $subcategory->slug)
                    ->exists()) {
                    $subcategory->slug = $base.'-'.$suffix;
                    $suffix++;
                }
            }
        });

        static::saving(function (Subcategory $subcategory): void {
            if ($subcategory->category_group_id) {
                $group = CategoryGroup::query()->find($subcategory->category_group_id);
                if ($group !== null) {
                    $subcategory->category_id = $group->category_id;
                }
            }
        });
    }
}
