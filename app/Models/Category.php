<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'image',
        'icon',
        'status',
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'position' => 'integer',
        'status' => 'string',
    ];

    /**
     * Full URL for the category image (storage path is stored in image).
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image;
        
        if (empty($this->image)) {
            return null;
        }

        return URL::asset('storage/'.ltrim($this->image, '/'));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CategoryGroup::class);
    }

    /**
     * All subcategories under this category (via groups).
     */
    public function subcategories(): HasManyThrough
    {
        return $this->hasManyThrough(
            Subcategory::class,
            CategoryGroup::class,
            'category_id',
            'category_group_id',
            'id',
            'id',
        );
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

        static::creating(function (Category $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
