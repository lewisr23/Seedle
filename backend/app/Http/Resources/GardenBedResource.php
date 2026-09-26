<?php

namespace App\Http\Resources;

use App\Models\GardenBed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GardenBed */
class GardenBedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hardiness_zone' => $this->hardiness_zone,
            'width_cm' => $this->width_cm,
            'length_cm' => $this->length_cm,
            'has_plot' => $this->hasPlot(),
            'plants' => GardenBedPlantResource::collection($this->whenLoaded('entries')),
            'created_at' => $this->created_at,
        ];
    }
}
