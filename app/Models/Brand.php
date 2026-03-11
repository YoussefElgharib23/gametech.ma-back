<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'position',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
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
