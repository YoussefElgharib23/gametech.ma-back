<?php

namespace App\Http\Controllers;

use App\Models\Slider;
use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    public function index(): JsonResponse
    {
        $sliders = Slider::with('image')->orderBy('id')->get();

        $data = $sliders->map(function (Slider $slider) {
            return [
                'id' => $slider->id,
                'side' => $slider->side?->value ?? (string) $slider->side,
                'link' => $slider->link,
                'image' => $slider->image
                    ? [
                        'id' => $slider->image->id,
                        'url' => $slider->image->url,
                        'path' => $slider->image->path,
                        'name' => $slider->image->name,
                    ]
                    : null,
            ];
        });

        return response()->json($data);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.slider_id' => ['required', 'integer', 'exists:sliders,id'],
            'items.*.link' => ['nullable', 'string'],
            'items.*.upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
        ]);

        $items = collect($validated['items']);

        $sliderIds = $items->pluck('slider_id')->unique()->all();
        $uploadIds = $items->pluck('upload_id')->filter()->unique()->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Slider> $sliders */
        $sliders = Slider::with('image')->whereIn('id', $sliderIds)->get()->keyBy('id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Upload> $uploads */
        $uploads = Upload::whereIn('id', $uploadIds)->get()->keyBy('id');

        foreach ($items as $item) {
            /** @var Slider $slider */
            $slider = $sliders[$item['slider_id']];

            $slider->link = $item['link'] ?? null;
            $slider->save();

            $uploadId = $item['upload_id'] ?? null;

            if ($uploadId) {
                /** @var Upload|null $upload */
                $upload = $uploads[$uploadId] ?? null;
                if (! $upload) {
                    continue;
                }

                // Detach previous image if different
                if ($slider->image && $slider->image->id !== $upload->id) {
                    $slider->image->uploadable()->dissociate();
                    $slider->image->save();
                }

                $upload->uploadable()->associate($slider);
                $upload->save();
            } elseif (array_key_exists('upload_id', $item) && $slider->image) {
                // Clear image if upload_id is explicitly null
                $slider->image->uploadable()->dissociate();
                $slider->image->save();
            }
        }

        return $this->index();
    }
}

