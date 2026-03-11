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
        $brands = Brand::orderBy('position')->orderBy('id')->get();

        $data = $brands->map(fn (Brand $b) => [
            'id' => $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'image' => $b->image,
            'status' => $b->status,
            'position' => $b->position,
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
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $brand = Brand::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']),
            'image' => $validated['image'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'position' => $validated['position'] ?? 0,
        ]);

        return response()->json([
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'image' => $brand->image,
            'status' => $brand->status,
            'position' => $brand->position,
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
            'image' => $brand->image,
            'status' => $brand->status,
            'position' => $brand->position,
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
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $brand->fill($validated);
        $brand->save();

        return response()->json([
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'image' => $brand->image,
            'status' => $brand->status,
            'position' => $brand->position,
        ]);
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
