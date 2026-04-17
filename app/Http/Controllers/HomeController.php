<?php

namespace App\Http\Controllers;

use App\Enums\ProductLandingSection;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Section;
use App\Models\Slider;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    /**
     * Aggregate data for the public homepage.
     */
    public function index(): JsonResponse
    {
        $sliders = Slider::with('image')
            ->orderBy('id')
            ->get();

        $sliderItems = $sliders->map(function (Slider $slider) {
            return [
                'id' => $slider->id,
                'side' => $slider->side?->value ?? (string) $slider->side,
                'link' => $slider->link,
                'image' => $slider->image
                    ? [
                        'id' => $slider->image->id,
                        'url' => $slider->image->url,
                        'path' => $slider->image->path,
                        'name' => $slider->image->name,
                    ]
                    : null,
            ];
        });

        $mainSides = ['left', 'center', 'right-top', 'right-bottom'];

        $main = $sliderItems->filter(
            fn (array $item) => in_array($item['side'], $mainSides, true)
        )->values();

        $threeCard = $sliderItems->filter(
            fn (array $item) => str_starts_with($item['side'], 'three-card-')
        )->values();

        $banner = $sliderItems->firstWhere('side', 'banner');

        $brands = Brand::active()
            ->whereNotNull('image')
            ->withCount('products')
            ->orderBy('products_count')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'image' => $b->image_url,
            ]);

        $categories = Category::active()
            ->orderBy('position')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'image' => $c->image_url,
                'icon' => $c->icon,
            ]);

        $landingProducts = collect(ProductLandingSection::cases())
            ->mapWithKeys(fn (ProductLandingSection $section) => [
                $section->value => $this->landingProductsForSection($section),
            ])
            ->all();

        $productsPerCategory = $this->productsPerCategoryForLanding();

        return response()->json([
            'sliders' => [
                'main' => $main,
                'three_card' => $threeCard,
                'banner' => $banner,
            ],
            'categories' => $categories,
            'brands' => $brands,
            'landing_products' => $landingProducts,
            'products_per_category' => $productsPerCategory,
        ]);
    }

    /**
     * Active products shown in the homepage “Nos sélections / Nouvel arrivage / Best seller” blocks.
     *
     * @return list<array{id: int, slug: string, title: string, image: string|null, stockStatus: string, currentPrice: string, oldPrice: string|null}>
     */
    private function landingProductsForSection(ProductLandingSection $section): array
    {
        return Product::query()
            ->where('status', 'active')
            ->where('section', $section->value)
            ->with(['uploads'])
            ->orderByRaw('position IS NULL')
            ->orderBy('position')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get()
            ->map(fn (Product $product) => $this->mapProductForLandingCarousel($product))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, slug: string, title: string, image: string|null, stockStatus: string, currentPrice: string, oldPrice: string|null}
     */
    private function mapProductForLandingCarousel(Product $product): array
    {
        $firstImage = $product->uploads->first();
        $isPromo = $product->compare_at_price !== null
            && (float) $product->compare_at_price > (float) $product->price;

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'title' => $product->title,
            'image' => $firstImage?->url,
            'stockStatus' => $product->stock_status_label,
            'currentPrice' => $product->price_label,
            'oldPrice' => $isPromo ? $product->compare_at_price_label : null,
        ];
    }

    /**
     * Top 5 active categories ranked by number of active products (highest first), each with up to 20 products for the homepage block.
     *
     * @return list<array{slug: string, name: string, products: list<array{id: int, slug: string, title: string, image: string|null, stockStatus: string, currentPrice: string, oldPrice: string|null}>}>
     */
    private function productsPerCategoryForLanding(): array
    {
        $categories = Category::active()
            ->whereHas('products', fn ($q) => $q->where('status', 'active'))
            ->withCount([
                'products as active_products_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->orderByDesc('active_products_count')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $blocks = [];

        foreach ($categories as $category) {
            $products = Product::query()
                ->where('status', 'active')
                ->where('category_id', $category->id)
                ->with(['uploads'])
                ->orderByRaw('position IS NULL')
                ->orderBy('position')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(fn (Product $product) => $this->mapProductForLandingCarousel($product))
                ->values()
                ->all();

            if ($products === []) {
                continue;
            }

            $blocks[] = [
                'slug' => $category->slug,
                'name' => $category->name,
                'products' => $products,
            ];
        }

        return $blocks;
    }

    /**
     * Archive page: list products for a given entity type & slug.
     *
     * @param  string  $entityType  category|subcategory|category_group|brand (or plural forms)
     */
    public function archive(Request $request, string $entityType, string $entitySlug): JsonResponse
    {
        $type = strtolower($entityType);
        $slug = $entitySlug;

        $query = Product::query()
            ->where('status', 'active')
            ->with(['brand', 'uploads']);

        $entityLabel = null;
        $normalizedType = null;

        if (in_array($type, ['category', 'categories'], true)) {
            $category = Category::active()->where('slug', $slug)->firstOrFail();
            $query->where('category_id', $category->id);
            $entityLabel = $category->name;
            $normalizedType = 'category';
        } elseif (in_array($type, ['subcategory', 'subcategories', 'sub'], true)) {
            $subcategory = Subcategory::active()->where('slug', $slug)->firstOrFail();
            $query->where('subcategory_id', $subcategory->id);
            $entityLabel = $subcategory->name;
            $normalizedType = 'subcategory';
        } elseif (in_array($type, ['category_group', 'category-group', 'category_groups', 'category-groups', 'group', 'groups'], true)) {
            $group = CategoryGroup::active()->where('slug', $slug)->firstOrFail();
            $query->where('category_group_id', $group->id);
            $entityLabel = $group->name;
            $normalizedType = 'category_group';
        } elseif (in_array($type, ['brand', 'brands'], true)) {
            $brand = Brand::active()->where('slug', $slug)->firstOrFail();
            $query->where('brand_id', $brand->id);
            $entityLabel = $brand->name;
            $normalizedType = 'brand';
        } elseif (in_array($type, ['section', 'sections'], true)) {
            if (! Schema::hasTable('sections')) {
                abort(404);
            }

            $section = Section::query()->where('is_active', true)->where('slug', $slug)->firstOrFail();
            $query->whereHas('sections', fn ($q) => $q->where('sections.id', $section->id));
            $entityLabel = $section->label;
            $normalizedType = 'section';
        } else {
            abort(404);
        }

        // Base stats (min/max price) BEFORE additional filters
        $statsQuery = clone $query;
        $sidebarQuery = clone $query;
        $brandsQuery = clone $query;

        // Brands that have at least one product in this entity (for filter UI)
        $brandIds = $brandsQuery->distinct()->pluck('brand_id')->filter()->values();
        $availableBrands = Brand::whereIn('id', $brandIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $b) => [
                'name' => $b->name,
                'image' => $b->image_url,
            ])
            ->values();

        $stats = $statsQuery
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        // Optional price filters
        $minPrice = $request->query('min_price');
        if (is_numeric($minPrice)) {
            $query->where('price', '>=', (float) $minPrice);
        }

        $maxPrice = $request->query('max_price');
        if (is_numeric($maxPrice)) {
            $query->where('price', '<=', (float) $maxPrice);
        }

        // Optional brand filters: brands[]=AMD&brands[]=Intel (by name)
        $brands = $request->query('brands', []);
        if (is_string($brands)) {
            $brands = [$brands];
        }
        if (is_array($brands) && count($brands) > 0) {
            $query->whereHas('brand', function ($q) use ($brands) {
                $q->whereIn('name', $brands);
            });
        }

        // Optional stock status filter
        $stockStatus = $request->query('stock_status');
        if (in_array($stockStatus, ['in_stock', 'out_of_stock', 'preorder'], true)) {
            $query->where('stock_status', $stockStatus);
        }

        // Optional promotion filter: 'promo' => compare_at_price > price, 'no_promo' => no discount
        $promo = $request->query('promo');
        if ($promo === 'promo') {
            $query->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price');
        } elseif ($promo === 'no_promo') {
            $query->where(function ($q) {
                $q->whereNull('compare_at_price')
                    ->orWhereColumn('compare_at_price', '<=', 'price');
            });
        }

        // Optional sort: price_asc, price_desc, latest, oldest
        $sort = $request->query('sort');
        if ($sort === 'price_asc') {
            $query->orderBy('price')->orderByDesc('id');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('price')->orderByDesc('id');
        } elseif ($sort === 'latest') {
            $query->orderByDesc('id');
        } elseif ($sort === 'oldest') {
            $query->orderBy('id');
        } else {
            $query->orderBy('position')->orderByDesc('id');
        }

        $sidebarProducts = $sidebarQuery
            ->with(['brand', 'uploads'])
            ->orderBy('position')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        // Pagination
        $perPage = (int) $request->query('per_page', 24);
        $perPage = $perPage >= 1 && $perPage <= 50 ? $perPage : 24;
        $page = (int) $request->query('page', 1);
        $page = $page >= 1 ? $page : 1;
        $total = (clone $query)->count();
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $page = min($page, $lastPage);
        $products = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $items = $products->map(function (Product $product) {
            $firstImage = $product->uploads->first();

            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->title,
                'image' => $firstImage?->url,
                'brand' => $product->brand?->name ?? '',
                'brand_image' => $product->brand?->image_url,
                'stockStatus' => $product->stock_status_label,
                'price' => (float) $product->price,
                'priceLabel' => $product->price_label,
                'oldPriceLabel' => $product->compare_at_price_label,
            ];
        });

        $sidebarItems = $sidebarProducts->map(function (Product $product) {
            $firstImage = $product->uploads->first();

            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->title,
                'image' => $firstImage?->url,
                'brand' => $product->brand?->name ?? '',
                'brand_image' => $product->brand?->image_url,
                'stockStatus' => $product->stock_status_label,
                'price' => (float) $product->price,
                'priceLabel' => $product->price_label,
                'oldPriceLabel' => $product->compare_at_price_label,
            ];
        });

        return response()->json([
            'entity' => [
                'type' => $normalizedType,
                'slug' => $slug,
                'label' => $entityLabel,
            ],
            'products' => $items,
            'sidebar_products' => $sidebarItems,
            'brands' => $availableBrands,
            'meta' => [
                'min_price' => $stats?->min_price !== null ? (float) $stats->min_price : null,
                'max_price' => $stats?->max_price !== null ? (float) $stats->max_price : null,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                ],
            ],
        ]);
    }

    /**
     * Categories with groups and subcategories for nav/mega menu.
     * Sorted by total product count under each category (most products first).
     */
    public function categoriesWithChildren(): JsonResponse
    {
        $categories = Category::active()
            ->with([
                'groups' => fn ($q) => $q->active()
                    ->orderByRaw('position IS NULL')
                    ->orderBy('position')
                    ->orderBy('name')
                    ->with([
                        'subcategories' => fn ($q2) => $q2->active()
                            ->orderByRaw('position IS NULL')
                            ->orderBy('position')
                            ->orderBy('name')
                            ->withCount('products'),
                    ])
                    ->withCount('products'),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->sortByDesc(fn (Category $c) => $c->groups->sum('products_count'))
            ->values();

        $items = $categories->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'image' => $c->image,
            'icon' => $c->icon,
            'groups' => $c->groups->map(fn (CategoryGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'icon' => $g->icon,
                'products_count' => (int) $g->products_count,
                'subcategories' => $g->subcategories->map(fn (Subcategory $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'products_count' => (int) $s->products_count,
                ])->values(),
            ])->values(),
        ])->values();

        return response()->json(['categories' => $items]);
    }

    /**
     * Products filtered by configurator_category, with optional search. Paginated.
     */
    public function configuratorProducts(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $search = trim((string) $request->query('q', ''));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));

        $query = Product::query()
            ->where('status', 'active')
            ->with(['uploads', 'brand']);

        if ($category !== null && $category !== '') {
            $query->where('configurator_category', $category);
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $products = $query
            ->orderByRaw('position IS NULL')
            ->orderBy('position')
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function (Product $product) {
                $firstImage = $product->uploads->first();
                $isPromo = $product->compare_at_price !== null
                    && (float) $product->compare_at_price > (float) $product->price;

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'title' => $product->title,
                    'image' => $firstImage?->url,
                    'brand_name' => $product->brand?->name,
                    'brand_image' => $product->brand?->image_url,
                    'stockStatus' => $product->stock_status_label,
                    'currentPrice' => $product->price_label,
                    'price' => (float) $product->price,
                    'oldPrice' => $isPromo ? $product->compare_at_price_label : null,
                    'configurator_category' => $product->configurator_category,
                ];
            })
            ->values();

        return response()->json([
            'data' => $products,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Global product search for the header.
     *
     * - When q is empty: returns latest active products.
     * - When q is provided: search by title and SKU.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 5);
        if ($limit <= 0 || $limit > 20) {
            $limit = 5;
        }

        $builder = Product::query()
            ->where('status', 'active')
            ->with(['brand', 'uploads']);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $builder->where(function ($sub) use ($term) {
                $sub->where('title', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        $total = (clone $builder)->count();

        $products = $builder
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $items = $products
            ->map(function (Product $product) {
                $firstImage = $product->uploads->first();
                $isPromo = $product->compare_at_price !== null
                    && (float) $product->compare_at_price > (float) $product->price;

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'title' => $product->title,
                    'image' => $firstImage?->url,
                    'priceLabel' => $product->price_label,
                    'oldPriceLabel' => $product->compare_at_price_label,
                    'isPromo' => $isPromo,
                ];
            })->values();

        return response()->json([
            'items' => $items,
            'has_more' => $total > $limit,
        ]);
    }
}
