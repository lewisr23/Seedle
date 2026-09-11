<?php

namespace App\Http\Resources;

use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guide */
class GuideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'read_minutes' => $this->read_minutes,
            'plant' => new PlantResource($this->whenLoaded('plant')),
            'published_at' => $this->published_at,
        ];
    }
}
