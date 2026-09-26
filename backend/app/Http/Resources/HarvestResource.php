<?php

namespace App\Http\Resources;

use App\Models\Harvest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Harvest */
class HarvestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_id' => $this->garden_bed_plant_id,
            'harvested_at' => $this->harvested_at->toDateString(),
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'notes' => $this->notes,
            'plant' => $this->whenLoaded('entry', fn () => new PlantResource($this->entry->plant)),
            'bed' => $this->whenLoaded('entry', fn () => $this->entry->relationLoaded('gardenBed') && $this->entry->gardenBed
                ? ['id' => $this->entry->gardenBed->id, 'name' => $this->entry->gardenBed->name]
                : null),
        ];
    }
}
