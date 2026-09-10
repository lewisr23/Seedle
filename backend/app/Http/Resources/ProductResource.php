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
            'price_pence' => $this->price_pence,
            'price_pounds' => $this->priceInPounds(),
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'images' => $this->images,
            'seller' => new UserResource($this->whenLoaded('seller')),
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'created_at' => $this->created_at,
        ];
    }
}
