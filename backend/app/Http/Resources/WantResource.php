<?php

namespace App\Http\Resources;

use App\Models\Want;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Want */
class WantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'is_open' => $this->is_open,
            'is_mine' => $request->user()?->id === $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'created_at' => $this->created_at,
        ];
    }
}
