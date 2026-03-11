<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    /**
     * List all brands.
     */
    public function index(): JsonResponse
    {
        $brands = Brand::orderBy('name')->orderBy('id')->get();

        $data = $brands->map(fn (Brand $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'image' => $b->image_url,
            'status' => $b->status,
        ]);

        return response()->json($data);
    }

    /**
     * Store a new brand.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $imagePath = isset($validated['image']) ? $this->normalizeImageToStoragePath($validated['image']) : null;

        $brand = Brand::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']),
            'image' => $imagePath,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'image' => $brand->image_url,
            'status' => $brand->status,
        ], 201);
    }

    /**
     * Show a single brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'image' => $brand->image_url,
            'status' => $brand->status,
        ]);
    }

    /**
     * Update a brand.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
        ]);

        if (array_key_exists('image', $validated)) {
            $validated['image'] = $validated['image'] ? $this->normalizeImageToStoragePath($validated['image']) : null;
        }
        $brand->fill($validated);
        $brand->save();

        return response()->json([
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'image' => $brand->image_url,
            'status' => $brand->status,
        ]);
    }

    /**
     * Store only the storage public path (e.g. "brands/amd.png"). If a full URL is sent, extract the path.
     */
    private function normalizeImageToStoragePath(string $value): string
    {
        if (preg_match('#/storage/(.+)$#', $value, $m)) {
            return $m[1];
        }

        return $value;
    }

    /**
     * Delete a brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(['status' => true], 204);
    }
}
