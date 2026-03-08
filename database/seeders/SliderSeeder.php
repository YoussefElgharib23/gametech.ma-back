<?php

namespace Database\Seeders;

use App\Enums\SliderSide;
use App\Models\Slider;
use App\Services\UploadService;
use Illuminate\Database\Seeder;

class SliderSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'side' => SliderSide::Left,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::Center,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::RightTop,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::RightBottom,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::Banner,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::ThreeCard1,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::ThreeCard2,
                'link' => '',
                'image_url' => '',
            ],
            [
                'side' => SliderSide::ThreeCard3,
                'link' => '',
                'image_url' => '',
            ],
        ];

        /** @var UploadService $uploads */
        $uploads = app(UploadService::class);

        foreach ($items as $item) {
            /** @var \App\Models\Slider $slider */
            $slider = Slider::firstOrCreate(
                ['side' => $item['side']],
                [
                    'link' => $item['link'] ?: null,
                ],
            );

            if (! empty($item['image_url']) && ! $slider->image) {
                $uploads->storeFromUrl(
                    $item['image_url'],
                    $slider,
                    directory: 'sliders',
                    relation: 'image',
                );
            }
        }
    }
}

