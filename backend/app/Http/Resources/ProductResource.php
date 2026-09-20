<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'category' => $this->category->value,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'images' => $this->imageUrls(),
            'rating_average' => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 1) : null,
            'reviews_count' => $this->reviews_count ?? null,
            'seller' => new UserResource($this->whenLoaded('seller')),
            // Only present on a radius search, and only ever a distance: the
            // seller's actual point is never exposed.
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 1)
            ),
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'created_at' => $this->created_at,
        ];
    }
}
