<?php

namespace App\Models;

use App\Concerns\Filterable;
use App\Enums\ProductLandingSection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'category_group_id',
        'brand_id',
        'price',
        'compare_at_price',
        'stock_status',
        'stock_quantity',
        'status',
        'is_featured',
        'position',
        'section',
        'configurator_category',
        'configurator_category_classified_at',
        'published_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'category_id' => 'integer',
        'subcategory_id' => 'integer',
        'category_group_id' => 'integer',
        'brand_id' => 'integer',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_featured' => 'boolean',
        'position' => 'integer',
        'section' => ProductLandingSection::class,
        'configurator_category_classified_at' => 'datetime',
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

    public function categoryGroup(): BelongsTo
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function uploads(): MorphMany
    {
        return $this->morphMany(Upload::class, 'uploadable')->orderBy('position');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'product_section');
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

    public function scopeByCategoryGroup($query, int $categoryGroupId)
    {
        return $query->where('category_group_id', $categoryGroupId);
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
            $slug = $baseSlug.'-'.$suffix;
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

        static::saving(function (Product $product): void {
            if ($product->subcategory_id) {
                $sub = Subcategory::query()->find($product->subcategory_id);
                if ($sub !== null) {
                    $product->category_group_id = $sub->category_group_id;
                    $product->category_id = $sub->category_id;
                }
            } elseif ($product->category_group_id) {
                $group = CategoryGroup::query()->find($product->category_group_id);
                if ($group !== null) {
                    $product->category_id = $group->category_id;
                }
            }
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('title') && empty($product->slug)) {
                $product->slug = static::makeUniqueSlug(Str::slug($product->title), $product->id);
            } elseif ($product->isDirty('slug') && ! empty($product->slug)) {
                $product->slug = static::makeUniqueSlug($product->slug, $product->id);
            }
        });
    }
}
