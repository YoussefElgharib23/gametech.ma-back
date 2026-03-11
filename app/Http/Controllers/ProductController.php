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
     * Get a single product by slug for the public product page.
     * Only returns products with status 'active'.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with(['category', 'subcategory', 'brand', 'uploads'])
            ->first();

        if (!$product) {
            throw new NotFoundHttpException('Product not found.');
        }

        return (new ProductResource($product))->response();
    }
}
