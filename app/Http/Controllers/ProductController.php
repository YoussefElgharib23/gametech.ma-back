<?php

namespace App\Http\Controllers;

use App\Http\Filters\ProductFilter;
use App\Models\Product;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List products (paginated). Filters via query string (search, status, category_id, brand_id).
     * Query: page, per_page (default 15).
     *
     * @see https://pineco.de/filtering-eloquent-queries-based-on-http-requests/
     */
    public function index(Request $request, ProductFilter $filter): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $paginator = Product::with(['category', 'subcategory', 'brand', 'uploads'])
            ->filter($filter)
            ->orderBy('position')
            ->orderBy('id')
            ->paginate($perPage)
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'sku' => $p->sku,
                'title' => $p->title,
                'description' => $p->description,
                'short_description' => $p->short_description,
                'category_id' => $p->category_id,
                'category_name' => $p->category?->name,
                'subcategory_id' => $p->subcategory_id,
                'subcategory_name' => $p->subcategory?->name,
                'brand_id' => $p->brand_id,
                'brand_name' => $p->brand?->name,
                'brand_image' => $p->brand?->image,
                'price' => $p->price,
                'price_label' => $p->price_label,
                'compare_at_price' => $p->compare_at_price,
                'compare_at_price_label' => $p->compare_at_price_label,
                'stock_status' => $p->stock_status,
                'stock_status_label' => $p->stock_status_label,
                'stock_quantity' => $p->stock_quantity,
                'status' => $p->status,
                'is_featured' => $p->is_featured,
                'position' => $p->position,
                'published_at' => $p->published_at?->toIso8601String(),
                'images' => $p->uploads->map(fn ($u) => $u->url)->toArray(),
            ]);

        return response()->json($paginator);
    }

    /**
     * Store a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:products,sku'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:subcategories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_status' => ['nullable', 'in:in_stock,out_of_stock,preorder'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,draft'],
            'is_featured' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
            'upload_ids' => ['nullable', 'array'],
            'upload_ids.*' => ['integer', 'exists:uploads,id'],
        ]);

        $product = Product::create([
            'title' => $validated['title'],
            'sku' => $validated['sku'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['title']),
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'subcategory_id' => $validated['subcategory_id'] ?? null,
            'brand_id' => $validated['brand_id'] ?? null,
            'price' => $validated['price'],
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'stock_status' => $validated['stock_status'] ?? 'in_stock',
            'stock_quantity' => $validated['stock_quantity'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'is_featured' => $validated['is_featured'] ?? false,
            'position' => $validated['position'] ?? 0,
            'published_at' => $validated['published_at'] ?? null,
        ]);

        // Attach images
        if (!empty($validated['upload_ids'])) {
            foreach ($validated['upload_ids'] as $index => $uploadId) {
                Upload::where('id', $uploadId)->update([
                    'uploadable_type' => Product::class,
                    'uploadable_id' => $product->id,
                    'position' => $index,
                ]);
            }
        }

        $product->load(['category', 'subcategory', 'brand', 'uploads']);

        return response()->json([
            'id' => $product->id,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'title' => $product->title,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'subcategory_id' => $product->subcategory_id,
            'subcategory_name' => $product->subcategory?->name,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
            'brand_image' => $product->brand?->image,
            'price' => $product->price,
            'price_label' => $product->price_label,
            'compare_at_price' => $product->compare_at_price,
            'compare_at_price_label' => $product->compare_at_price_label,
            'stock_status' => $product->stock_status,
            'stock_status_label' => $product->stock_status_label,
            'stock_quantity' => $product->stock_quantity,
            'status' => $product->status,
            'is_featured' => $product->is_featured,
            'position' => $product->position,
            'published_at' => $product->published_at?->toIso8601String(),
            'images' => $product->uploads->map(fn ($u) => $u->url)->toArray(),
        ], 201);
    }

    /**
     * Show a single product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'subcategory', 'brand', 'uploads']);

        return response()->json([
            'id' => $product->id,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'title' => $product->title,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'subcategory_id' => $product->subcategory_id,
            'subcategory_name' => $product->subcategory?->name,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
            'brand_image' => $product->brand?->image,
            'price' => $product->price,
            'price_label' => $product->price_label,
            'compare_at_price' => $product->compare_at_price,
            'compare_at_price_label' => $product->compare_at_price_label,
            'stock_status' => $product->stock_status,
            'stock_status_label' => $product->stock_status_label,
            'stock_quantity' => $product->stock_quantity,
            'status' => $product->status,
            'is_featured' => $product->is_featured,
            'position' => $product->position,
            'published_at' => $product->published_at?->toIso8601String(),
            'images' => $product->uploads->map(fn ($u) => $u->url)->toArray(),
        ]);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:255', 'unique:products,sku,' . $product->id],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'short_description' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['sometimes', 'nullable', 'integer', 'exists:subcategories,id'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'compare_at_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock_status' => ['sometimes', 'nullable', 'in:in_stock,out_of_stock,preorder'],
            'stock_quantity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,draft'],
            'is_featured' => ['sometimes', 'nullable', 'boolean'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'upload_ids' => ['sometimes', 'nullable', 'array'],
            'upload_ids.*' => ['integer', 'exists:uploads,id'],
        ]);

        $product->fill($validated);
        $product->save();

        // Update images if provided
        if (isset($validated['upload_ids'])) {
            // Detach old images
            Upload::where('uploadable_type', Product::class)
                ->where('uploadable_id', $product->id)
                ->update([
                    'uploadable_type' => null,
                    'uploadable_id' => null,
                    'position' => 0,
                ]);

            // Attach new images
            foreach ($validated['upload_ids'] as $index => $uploadId) {
                Upload::where('id', $uploadId)->update([
                    'uploadable_type' => Product::class,
                    'uploadable_id' => $product->id,
                    'position' => $index,
                ]);
            }
        }

        $product->load(['category', 'subcategory', 'brand', 'uploads']);

        return response()->json([
            'id' => $product->id,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'title' => $product->title,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'subcategory_id' => $product->subcategory_id,
            'subcategory_name' => $product->subcategory?->name,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
            'brand_image' => $product->brand?->image,
            'price' => $product->price,
            'price_label' => $product->price_label,
            'compare_at_price' => $product->compare_at_price,
            'compare_at_price_label' => $product->compare_at_price_label,
            'stock_status' => $product->stock_status,
            'stock_status_label' => $product->stock_status_label,
            'stock_quantity' => $product->stock_quantity,
            'status' => $product->status,
            'is_featured' => $product->is_featured,
            'position' => $product->position,
            'published_at' => $product->published_at?->toIso8601String(),
            'images' => $product->uploads->map(fn ($u) => $u->url)->toArray(),
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Detach images before deleting
        Upload::where('uploadable_type', Product::class)
            ->where('uploadable_id', $product->id)
            ->update([
                'uploadable_type' => null,
                'uploadable_id' => null,
                'position' => 0,
            ]);

        $product->delete();

        return response()->json(['status' => true], 204);
    }

    /**
     * Bulk update product status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'status' => ['required', 'in:active,inactive,draft'],
        ]);

        Product::whereIn('id', $validated['product_ids'])
            ->update(['status' => $validated['status']]);

        return response()->json(['status' => true, 'updated' => count($validated['product_ids'])]);
    }

    /**
     * Bulk update product featured status.
     */
    public function bulkUpdateFeatured(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'is_featured' => ['required', 'boolean'],
        ]);

        Product::whereIn('id', $validated['product_ids'])
            ->update(['is_featured' => $validated['is_featured']]);

        return response()->json(['status' => true, 'updated' => count($validated['product_ids'])]);
    }
}
