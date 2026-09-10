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
            'plants' => $this->whenLoaded('entries', fn () => $this->entries->map(fn ($entry) => [
                'entry_id' => $entry->id,
                'plant' => new PlantResource($entry->plant),
                'planted_at' => $entry->planted_at,
                'notes' => $entry->notes,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
