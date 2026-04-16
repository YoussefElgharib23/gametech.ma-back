<?php

namespace App\Http\Controllers;

use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    /**
     * List subcategories, optionally filtered by category_id or category_group_id.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subcategory::with(['category', 'categoryGroup'])
            ->orderBy('position')
            ->orderBy('id');

        if ($request->filled('category_group_id')) {
            $query->where('category_group_id', (int) $request->input('category_group_id'));
        } elseif ($request->filled('category_id')) {
            $query->whereHas('categoryGroup', function ($q) use ($request): void {
                $q->where('category_id', (int) $request->input('category_id'));
            });
        }

        $subcategories = $query->get();

        $data = $subcategories->map(fn (Subcategory $s) => [
            'id' => $s->id,
            'category_id' => $s->category_id,
            'category_name' => $s->category?->name,
            'category_group_id' => $s->category_group_id,
            'category_group_name' => $s->categoryGroup?->name,
            'name' => $s->name,
            'slug' => $s->slug,
            'status' => $s->status,
            'position' => $s->position,
        ]);

        return response()->json($data);
    }

    /**
     * Store a new subcategory.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_group_id' => ['required', 'integer', 'exists:category_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $subcategory = Subcategory::create([
            'category_group_id' => $validated['category_group_id'],
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']),
            'status' => $validated['status'] ?? 'active',
            'position' => $validated['position'] ?? 0,
        ]);

        $subcategory->load(['category', 'categoryGroup']);

        return response()->json([
            'id' => $subcategory->id,
            'category_id' => $subcategory->category_id,
            'category_name' => $subcategory->category?->name,
            'category_group_id' => $subcategory->category_group_id,
            'category_group_name' => $subcategory->categoryGroup?->name,
            'name' => $subcategory->name,
            'slug' => $subcategory->slug,
            'status' => $subcategory->status,
            'position' => $subcategory->position,
        ], 201);
    }

    /**
     * Show a single subcategory.
     */
    public function show(Subcategory $subcategory): JsonResponse
    {
        $subcategory->load(['category', 'categoryGroup']);

        return response()->json([
            'id' => $subcategory->id,
            'category_id' => $subcategory->category_id,
            'category_name' => $subcategory->category?->name,
            'category_group_id' => $subcategory->category_group_id,
            'category_group_name' => $subcategory->categoryGroup?->name,
            'name' => $subcategory->name,
            'slug' => $subcategory->slug,
            'status' => $subcategory->status,
            'position' => $subcategory->position,
        ]);
    }

    /**
     * Update a subcategory.
     */
    public function update(Request $request, Subcategory $subcategory): JsonResponse
    {
        $validated = $request->validate([
            'category_group_id' => ['sometimes', 'integer', 'exists:category_groups,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $subcategory->fill($validated);
        $subcategory->save();
        $subcategory->load(['category', 'categoryGroup']);

        return response()->json([
            'id' => $subcategory->id,
            'category_id' => $subcategory->category_id,
            'category_name' => $subcategory->category?->name,
            'category_group_id' => $subcategory->category_group_id,
            'category_group_name' => $subcategory->categoryGroup?->name,
            'name' => $subcategory->name,
            'slug' => $subcategory->slug,
            'status' => $subcategory->status,
            'position' => $subcategory->position,
        ]);
    }

    /**
     * Delete a subcategory.
     */
    public function destroy(Subcategory $subcategory): JsonResponse
    {
        $subcategory->delete();

        return response()->noContent();
    }
}
