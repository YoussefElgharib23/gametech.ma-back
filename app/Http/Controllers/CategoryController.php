<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List categories ordered by position then id.
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('position')
            ->orderBy('id')
            ->get();

        $data = $categories->map(fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'image' => $c->image,
            'icon' => $c->icon,
            'status' => $c->status,
            'position' => $c->position,
        ]);

        return response()->json($data);
    }

    /**
     * Store a new category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:categories,slug'],
            'image' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:active,inactive'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']),
            'image' => $validated['image'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'position' => $validated['position'] ?? 0,
        ]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => $category->image,
            'icon' => $category->icon,
            'status' => $category->status,
            'position' => $category->position,
        ], 201);
    }

    /**
     * Show a single category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load([
            'groups' => fn ($q) => $q->orderBy('position')->orderBy('name')->with([
                'subcategories' => fn ($q2) => $q2->orderBy('position')->orderBy('name'),
            ]),
        ]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => $category->image,
            'icon' => $category->icon,
            'status' => $category->status,
            'position' => $category->position,
            'groups' => $category->groups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'icon' => $g->icon,
                'status' => $g->status,
                'position' => $g->position,
                'subcategories' => $g->subcategories->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'status' => $s->status,
                    'position' => $s->position,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Update a category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:categories,slug,'.$category->id],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $category->fill($validated);
        $category->save();

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => $category->image,
            'icon' => $category->icon,
            'status' => $category->status,
            'position' => $category->position,
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'status' => true,
        ]);
    }
}
