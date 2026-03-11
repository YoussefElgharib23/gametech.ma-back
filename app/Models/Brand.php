<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;

class Brand extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'image',
        'status',
    ];

    /**
     * Full URL for the brand logo (storage path is stored in image).
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        return URL::asset('storage/' . ltrim($this->image, '/'));
    }

    /**
     * @var array<string, string>
     */
    protected $casts = [
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Boot the model and generate slug from name when creating.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Brand $brand): void {
            if (empty($brand->slug)) {
                $brand->slug = Str::slug($brand->name);
            }
            if (empty($brand->status)) {
                $brand->status = 'active';
            }
        });
    }
}
