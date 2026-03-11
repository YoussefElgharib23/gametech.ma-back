<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array for the public storefront.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $savingsLabel = null;
        if ($this->compare_at_price && (float) $this->compare_at_price > (float) $this->price) {
            $savings = (float) $this->compare_at_price - (float) $this->price;
            $savingsLabel = 'Économisez ' . number_format($savings, 0, ',', ' ') . ' MAD';
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'shortDescription' => $this->short_description ?? '',
            'images' => $this->whenLoaded('uploads', fn () => $this->uploads->map(fn ($u) => $u->url)->values()->toArray(), []),
            'brand' => $this->when($this->relationLoaded('brand') && $this->brand, fn () => [
                'name' => $this->brand->name,
                'image' => $this->brand->image_url,
            ]),
            'currentPrice' => $this->price_label,
            'oldPrice' => $this->compare_at_price_label,
            'savingsLabel' => $savingsLabel,
            'stockStatus' => $this->stock_status_label,
            'category' => $this->when($this->relationLoaded('category') && $this->category, fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug ?? Str::slug($this->category->name),
            ]),
        ];
    }
}
