<?php

namespace App\Services\Garden;

use App\Models\GardenBed;
use App\Models\Plant;
use Illuminate\Support\Collection;

/**
 * The "genuinely helpful" logic of the app: what can I plant right now,
 * and will it fight with what's already in this bed.
 */
class GardenHelperService
{
    /**
     * Plants suitable for the given hardiness zone that are normally
     * planted in the given month (defaults to the current month).
     */
    public function recommendedPlants(int $zone, ?int $month = null): Collection
    {
        $month ??= (int) now()->format('n');

        return Plant::all()->filter(
            fn (Plant $plant) => $plant->suitableForZone($zone) && $plant->plantableInMonth($month)
        )->values();
    }

    /**
     * Check a candidate plant against everything already in a bed and
     * report any bad-companion conflicts.
     *
     * @return array<int, array{plant: Plant, note: string}>
     */
    public function checkNewPlantAgainstBed(GardenBed $bed, Plant $candidate): array
    {
        $existingPlantIds = $bed->entries()->with('plant')->get()->pluck('plant.id')->unique();

        $badCompanionIds = $candidate->badCompanions()->pluck('plants.id');

        $conflicts = [];

        foreach ($existingPlantIds as $existingId) {
            if ($badCompanionIds->contains($existingId)) {
                $conflicts[] = [
                    'plant' => Plant::find($existingId),
                    'note' => "{$candidate->name} doesn't grow well near ".Plant::find($existingId)->name.'.',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * All bad-companion conflicts currently present within a bed
     * (e.g. after several plants have been added over time).
     *
     * @return array<int, array{plant_a: Plant, plant_b: Plant, note: string}>
     */
    public function conflictsWithinBed(GardenBed $bed): array
    {
        $plants = $bed->entries()->with('plant')->get()->pluck('plant')->unique('id')->values();

        $conflicts = [];

        foreach ($plants as $i => $plantA) {
            foreach ($plants as $j => $plantB) {
                if ($j <= $i) {
                    continue;
                }

                if ($plantA->badCompanions()->pluck('plants.id')->contains($plantB->id)) {
                    $conflicts[] = [
                        'plant_a' => $plantA,
                        'plant_b' => $plantB,
                        'note' => "{$plantA->name} and {$plantB->name} are poor companions.",
                    ];
                }
            }
        }

        return $conflicts;
    }
}
