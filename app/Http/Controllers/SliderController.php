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
        $sliders = Slider::with('image')
            ->orderBy('side')
            ->orderBy('id')
            ->get();

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
            'items.*.slider_id' => ['nullable', 'integer', 'exists:sliders,id'],
            'items.*.side' => ['required_without:items.*.slider_id', 'string'],
            'items.*.link' => ['nullable', 'string'],
            'items.*.upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'deleted_slider_ids' => ['sometimes', 'array'],
            'deleted_slider_ids.*' => ['integer', 'exists:sliders,id'],
        ]);

        $items = collect($validated['items']);

        $sliderIds = $items->pluck('slider_id')->filter()->unique()->all();
        $uploadIds = $items->pluck('upload_id')->filter()->unique()->all();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Slider> $sliders */
        $sliders = Slider::with('image')->whereIn('id', $sliderIds)->get()->keyBy('id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Upload> $uploads */
        $uploads = Upload::whereIn('id', $uploadIds)->get()->keyBy('id');

        $deletedIds = collect($validated['deleted_slider_ids'] ?? [])
            ->unique()
            ->values();

        if ($deletedIds->isNotEmpty()) {
            // Detach uploads first so they don't remain linked to deleted rows.
            $toDelete = Slider::with('image')->whereIn('id', $deletedIds)->get();
            foreach ($toDelete as $slider) {
                if ($slider->image) {
                    $slider->image->uploadable()->dissociate();
                    $slider->image->save();
                }
                $slider->delete();
            }
        }

        foreach ($items as $item) {
            $sliderId = $item['slider_id'] ?? null;

            /** @var Slider $slider */
            $slider = $sliderId ? ($sliders[$sliderId] ?? null) : null;

            if (! $slider) {
                $side = $item['side'] ?? null;
                if (! $side) {
                    continue;
                }

                $slider = Slider::create([
                    'side' => $side,
                    'link' => $item['link'] ?? null,
                ]);

                $sliders->put($slider->id, $slider);
            } else {
                $slider->link = $item['link'] ?? null;
                $slider->save();
            }

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

