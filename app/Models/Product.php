<?php

namespace App\Models;

use App\Concerns\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use Filterable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'sku',
        'title',
        'description',
        'short_description',
        'category_id',
        'subcategory_id',
        'brand_id',
        'price',
        'compare_at_price',
        'stock_status',
        'stock_quantity',
        'status',
        'is_featured',
        'position',
        'published_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'category_id' => 'integer',
        'subcategory_id' => 'integer',
        'brand_id' => 'integer',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_featured' => 'boolean',
        'position' => 'integer',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function uploads(): MorphMany
    {
        return $this->morphMany(Upload::class, 'uploadable')->orderBy('position');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', 'in_stock');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_status', 'out_of_stock');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeBySubcategory($query, $subcategoryId)
    {
        return $query->where('subcategory_id', $subcategoryId);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    // Accessors
    private static function formatPriceAsMad(float $amount): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, 'MAD');
    }

    public function getPriceLabelAttribute(): string
    {
        return self::formatPriceAsMad((float) $this->price);
    }

    public function getCompareAtPriceLabelAttribute(): ?string
    {
        if ($this->compare_at_price === null) {
            return null;
        }

        return self::formatPriceAsMad((float) $this->compare_at_price);
    }

    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'in_stock' => 'EN STOCK',
            'out_of_stock' => 'RUPTURE DE STOCK',
            'preorder' => 'PRÉ-COMMANDE',
            default => 'EN STOCK',
        };
    }

    /**
     * Ensure slug is unique. If base slug exists, appends -2, -3, etc.
     */
    public static function makeUniqueSlug(string $baseSlug, ?int $excludeId = null): string
    {
        $slug = $baseSlug;
        $query = static::query()->where('slug', $slug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        $suffix = 2;
        while ($query->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $query = static::query()->where('slug', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            $suffix++;
        }
        return $slug;
    }

    /**
     * Boot the model and generate unique slug from title when creating/updating.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Product $product): void {
            if (empty($product->slug)) {
                $product->slug = static::makeUniqueSlug(Str::slug($product->title));
            } else {
                $product->slug = static::makeUniqueSlug($product->slug);
            }
            if (empty($product->status)) {
                $product->status = 'draft';
            }
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('title') && empty($product->slug)) {
                $product->slug = static::makeUniqueSlug(Str::slug($product->title), $product->id);
            } elseif ($product->isDirty('slug') && !empty($product->slug)) {
                $product->slug = static::makeUniqueSlug($product->slug, $product->id);
            }
        });
    }
}
