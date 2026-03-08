<?php

namespace App\Http\Controllers;

use App\Models\LandingSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingSectionController extends Controller
{
    /**
     * List all landing sections (ordered by position).
     */
    public function index(): JsonResponse
    {
        $sections = LandingSection::orderBy('position')
            ->orderBy('id')
            ->get();

        $data = $sections->map(fn (LandingSection $s) => [
            'id' => $s->id,
            'key' => $s->key,
            'position' => $s->position,
            'config' => $s->config ?? [],
        ]);

        return response()->json($data);
    }

    /**
     * Get one section by key.
     */
    public function show(string $key): JsonResponse
    {
        $section = LandingSection::where('key', $key)->firstOrFail();

        return response()->json([
            'id' => $section->id,
            'key' => $section->key,
            'position' => $section->position,
            'config' => $section->config ?? [],
        ]);
    }

    /**
     * Create or update a section's config (and optionally position) by key.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'config' => ['sometimes', 'array'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $section = LandingSection::firstOrCreate(
            ['key' => $key],
            ['position' => 0, 'config' => []],
        );

        if (array_key_exists('config', $validated)) {
            $section->config = $validated['config'];
        }
        if (array_key_exists('position', $validated)) {
            $section->position = $validated['position'];
        }
        $section->save();

        return response()->json([
            'id' => $section->id,
            'key' => $section->key,
            'position' => $section->position,
            'config' => $section->config ?? [],
        ]);
    }
}
