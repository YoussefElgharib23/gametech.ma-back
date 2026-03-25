<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;

class StoreSettingController extends Controller
{
    /**
     * Public settings used on the storefront (header/footer).
     */
    public function public(): JsonResponse
    {
        $keys = [
            'company.email',
            'company.phone_1',
            'company.phone_2',
            'flash_sales.enabled',
            'flash_sales.expires_at',
            'socials.whatsapp',
        ];

        $settings = StoreSetting::query()
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn (StoreSetting $s) => [$s->key => $s->value]);

        return response()->json([
            'settings' => $settings,
        ]);
    }
}
