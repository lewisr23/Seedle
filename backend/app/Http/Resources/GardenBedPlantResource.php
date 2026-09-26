<?php

namespace App\Http\Resources;

use App\Models\GardenBedPlant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin GardenBedPlant */
class GardenBedPlantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Kept as entry_id rather than id: the planner deals in plants and
            // entries at once, and two bare "id" fields side by side is how you
            // end up deleting the wrong thing.
            'entry_id' => $this->id,
            'plant' => $this->whenLoaded('plant', fn () => new PlantResource($this->plant)),
            'x_cm' => $this->x_cm,
            'y_cm' => $this->y_cm,
            'planted_at' => $this->planted_at,
            'ready_on' => $this->readyOn()?->toDateString(),
            'notes' => $this->notes,
            'harvests' => HarvestResource::collection($this->whenLoaded('harvests')),
        ];
    }

    /**
     * The date this entry should come ready, if we know both when it went in
     * and how long it takes.
     */
    private function readyOn(): ?Carbon
    {
        $days = $this->relationLoaded('plant') ? $this->plant?->days_to_maturity : null;

        if ($this->planted_at === null || $days === null) {
            return null;
        }

        return Carbon::parse($this->planted_at)->addDays($days);
    }
}
