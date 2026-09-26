<?php

namespace App\Services\Garden;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The plant library's calendar, turned sideways.
 *
 * The library answers "when do I sow this". A grower wants the other view:
 * for the whole year at once, what goes in when, and what comes out when. The
 * harvest months are derived from days_to_maturity rather than stored, so
 * nothing here can drift out of step with the plant data.
 */
class SowingCalendarService
{
    /**
     * A row per plant, in an order that puts the grower's own plants first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(?User $user, ?int $zone = null): array
    {
        $plants = Plant::query()
            ->when($zone !== null, fn ($q) => $q->where('min_zone', '<=', $zone)->where('max_zone', '>=', $zone))
            ->orderBy('name')
            ->get();

        $plantings = $this->plantings($user);
        $savedIds = $user ? $user->savedPlants()->pluck('plants.id')->flip() : collect();

        return $plants->map(function (Plant $plant) use ($plantings, $savedIds, $zone) {
            $mine = $plantings->get($plant->id, collect());

            return [
                'plant' => [
                    'id' => $plant->id,
                    'name' => $plant->name,
                    'type' => $plant->type->value,
                    'days_to_maturity' => $plant->days_to_maturity,
                    'spacing_cm' => $plant->spacing_cm,
                ],
                'sow_months' => array_values($plant->planting_months),
                'harvest_months' => $this->harvestMonths($plant),
                'suitable_for_zone' => $zone === null || $plant->suitableForZone($zone),
                'saved' => $savedIds->has($plant->id),
                'in_beds' => $mine->isNotEmpty(),
                'plantings' => $mine->values()->all(),
            ];
        })
            // Whatever you are actually growing belongs at the top; the rest of
            // the library is reference material underneath it.
            ->sortBy(fn (array $row) => [$row['in_beds'] ? 0 : ($row['saved'] ? 1 : 2), $row['plant']['name']])
            ->values()
            ->all();
    }

    /**
     * What the grower has in the ground, keyed by plant.
     *
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function plantings(?User $user): Collection
    {
        if ($user === null) {
            return collect();
        }

        return $user->gardenBeds()
            ->with(['entries.plant'])
            ->get()
            ->flatMap(fn ($bed) => $bed->entries->map(fn ($entry) => [
                'entry_id' => $entry->id,
                'bed_id' => $bed->id,
                'bed_name' => $bed->name,
                'plant_id' => $entry->plant_id,
                'planted_at' => $entry->planted_at?->toDateString(),
                'ready_on' => $entry->planted_at && $entry->plant?->days_to_maturity
                    ? Carbon::parse($entry->planted_at)->addDays($entry->plant->days_to_maturity)->toDateString()
                    : null,
            ]))
            ->groupBy('plant_id');
    }

    /**
     * The months a plant sown in its normal windows should come ready.
     *
     * Garlic is the case worth keeping in mind: 240 days from an October
     * sowing lands in June the following year, so the arithmetic has to wrap
     * around the end of the year rather than run off the end of it.
     *
     * @return array<int, int>
     */
    private function harvestMonths(Plant $plant): array
    {
        if ($plant->days_to_maturity === null) {
            return [];
        }

        $offset = (int) round($plant->days_to_maturity / 30);

        return collect($plant->planting_months)
            ->map(fn (int $month) => (($month - 1 + $offset) % 12) + 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
