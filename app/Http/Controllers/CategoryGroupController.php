<?php

namespace App\Http\Controllers;

use App\Models\CategoryGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryGroupController extends Controller
{
    /**
     * List category groups, optionally scoped by category_id.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CategoryGroup::with('category')
            ->orderBy('position')
            ->orderBy('id');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        $groups = $query->get();

        $data = $groups->map(fn (CategoryGroup $g) => [
            'id' => $g->id,
            'category_id' => $g->category_id,
            'category_name' => $g->category?->name,
            'name' => $g->name,
            'slug' => $g->slug,
            'icon' => $g->icon,
            'status' => $g->status,
            'position' => $g->position,
        ]);

        return response()->json($data);
    }

    /**
     * Store a new category group.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:active,inactive'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $group = CategoryGroup::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']),
            'icon' => $validated['icon'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'position' => $validated['position'] ?? 0,
        ]);

        $group->load('category');

        return response()->json([
            'id' => $group->id,
            'category_id' => $group->category_id,
            'category_name' => $group->category?->name,
            'name' => $group->name,
            'slug' => $group->slug,
            'icon' => $group->icon,
            'status' => $group->status,
            'position' => $group->position,
        ], 201);
    }

    /**
     * Show a single category group.
     */
    public function show(CategoryGroup $categoryGroup): JsonResponse
    {
        $categoryGroup->load(['category', 'subcategories' => fn ($q) => $q->orderBy('position')->orderBy('name')]);

        return response()->json([
            'id' => $categoryGroup->id,
            'category_id' => $categoryGroup->category_id,
            'category_name' => $categoryGroup->category?->name,
            'name' => $categoryGroup->name,
            'slug' => $categoryGroup->slug,
            'icon' => $categoryGroup->icon,
            'status' => $categoryGroup->status,
            'position' => $categoryGroup->position,
            'subcategories' => $categoryGroup->subcategories->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'status' => $s->status,
                'position' => $s->position,
            ])->values(),
        ]);
    }

    /**
     * Update a category group.
     */
    public function update(Request $request, CategoryGroup $categoryGroup): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $categoryGroup->fill($validated);
        $categoryGroup->save();
        $categoryGroup->load('category');

        return response()->json([
            'id' => $categoryGroup->id,
            'category_id' => $categoryGroup->category_id,
            'category_name' => $categoryGroup->category?->name,
            'name' => $categoryGroup->name,
            'slug' => $categoryGroup->slug,
            'icon' => $categoryGroup->icon,
            'status' => $categoryGroup->status,
            'position' => $categoryGroup->position,
        ]);
    }

    /**
     * Delete a category group.
     */
    public function destroy(CategoryGroup $categoryGroup): JsonResponse
    {
        $categoryGroup->delete();

        return response()->json(['status' => true]);
    }
}
