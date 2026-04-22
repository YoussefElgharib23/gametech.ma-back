<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public API for storefront: product listing, product details by slug, etc.
 */
class ProductController extends Controller
{
    /**
     * @return list<array{id:int,slug:string,title:string,image:string|null,currentPrice:string,oldPrice:string|null,stockStatus:string}>
     */
    private function suggestedProducts(Product $product, int $limit = 12): array
    {
        $categoryId = $product->category_id;
        $subcategoryId = $product->subcategory_id;
        $brandId = $product->brand_id;

        $query = Product::query()
            ->where('status', 'active')
            ->where('id', '!=', $product->id)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with(['uploads']);

        // Rank: same subcategory first, then same brand, then promo, then in-stock, then newest.
        $query->orderByRaw(
            '(
                CASE WHEN subcategory_id = ? THEN 50 ELSE 0 END
              + CASE WHEN brand_id = ? THEN 20 ELSE 0 END
              + CASE WHEN compare_at_price IS NOT NULL AND compare_at_price > price THEN 8 ELSE 0 END
              + CASE WHEN stock_status = "in_stock" THEN 3 ELSE 0 END
            ) DESC',
            [$subcategoryId ?? 0, $brandId ?? 0],
        );

        $items = $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Product $p) {
                $firstImage = $p->uploads->first();

                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'image' => $firstImage?->url,
                    'currentPrice' => $p->price_label,
                    'oldPrice' => $p->compare_at_price_label,
                    'stockStatus' => $p->stock_status_label,
                ];
            })
            ->values();

        // If category filter was too restrictive, broaden by dropping it.
        if ($items->count() < $limit) {
            $fallback = Product::query()
                ->where('status', 'active')
                ->where('id', '!=', $product->id)
                ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->with(['uploads'])
                ->orderByDesc('id')
                ->limit($limit * 2)
                ->get()
                ->map(function (Product $p) {
                    $firstImage = $p->uploads->first();

                    return [
                        'id' => $p->id,
                        'slug' => $p->slug,
                        'title' => $p->title,
                        'image' => $firstImage?->url,
                        'currentPrice' => $p->price_label,
                        'oldPrice' => $p->compare_at_price_label,
                        'stockStatus' => $p->stock_status_label,
                    ];
                });

            $items = $items
                ->concat($fallback)
                ->unique('id')
                ->take($limit)
                ->values();
        }

        /** @var list<array{id:int,slug:string,title:string,image:string|null,currentPrice:string,oldPrice:string|null,stockStatus:string}> $out */
        $out = $items->all();

        return $out;
    }

    /**
     * Get a single product by slug for the public product page.
     * Only returns products with status 'active'.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with(['category', 'subcategory', 'categoryGroup', 'brand', 'uploads'])
            ->first();

        if (! $product) {
            throw new NotFoundHttpException('Product not found.');
        }

        return (new ProductResource($product))
            ->additional([
                'suggestedProducts' => $this->suggestedProducts($product),
            ])
            ->response();
    }
}
