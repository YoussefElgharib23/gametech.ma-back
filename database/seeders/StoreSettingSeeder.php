<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'store.name' => 'GameTech',
            'company.name' => 'GameTech',
            'company.email' => 'contact@gametech.ma',
            'company.phone_1' => '+212',
            'company.phone_2' => null,
            'company.address' => null,
            'flash_sales.enabled' => true,
            'flash_sales.expires_at' => null,
            'socials.facebook' => null,
            'socials.instagram' => null,
            'socials.tiktok' => null,
            'socials.youtube' => null,
            'socials.whatsapp' => null,
        ];

        foreach ($defaults as $key => $value) {
            StoreSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
