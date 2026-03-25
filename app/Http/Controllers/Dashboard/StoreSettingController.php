<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = StoreSetting::query()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (StoreSetting $s) => [$s->key => $s->value]);

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Upsert settings (update existing keys, create missing ones).
     *
     * Body: { settings: { "store.name": "GameTech", "socials.facebook": "..." } }
     */
    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        /** @var array<string, mixed> $settings */
        $settings = $validated['settings'];

        foreach ($settings as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key)) {
                continue;
            }

            StoreSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }

        $fresh = StoreSetting::query()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (StoreSetting $s) => [$s->key => $s->value]);

        return response()->json([
            'settings' => $fresh,
        ]);
    }
}
