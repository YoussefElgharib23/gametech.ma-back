<?php

namespace Database\Seeders;

use App\Models\LandingSection;
use Illuminate\Database\Seeder;

class LandingSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'key' => 'categories_carousel',
                'position' => 2,
                'config' => ['category_ids' => []],
            ],
            [
                'key' => 'products_per_category',
                'position' => 6,
                'config' => [
                    'category_ids' => [],
                    'default_category_id' => null,
                ],
            ],
        ];

        foreach ($sections as $item) {
            LandingSection::updateOrCreate(
                ['key' => $item['key']],
                [
                    'position' => $item['position'],
                    'config' => $item['config'],
                ],
            );
        }
    }
}
