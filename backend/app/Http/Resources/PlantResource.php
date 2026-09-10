<?php

namespace App\Http\Resources;

use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plant */
class PlantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scientific_name' => $this->scientific_name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'sun_requirement' => $this->sun_requirement->value,
            'water_needs' => $this->water_needs->value,
            'soil_type' => $this->soil_type,
            'min_zone' => $this->min_zone,
            'max_zone' => $this->max_zone,
            'days_to_maturity' => $this->days_to_maturity,
            'planting_months' => $this->planting_months,
            'description' => $this->description,
            'good_companions' => PlantResource::collection($this->whenLoaded('goodCompanions')),
            'bad_companions' => PlantResource::collection($this->whenLoaded('badCompanions')),
        ];
    }
}
