<?php

namespace App\Services\Garden;

use App\Models\GardenBed;
use App\Models\GardenBedPlant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The checks that only make sense once a bed is drawn to scale.
 *
 * The companion rules already say tomatoes and potatoes fall out. What a plan
 * adds is *where*: two plants at opposite ends of a ten metre bed are not
 * really neighbours, and two courgettes 20cm apart are a problem even though
 * nothing in the companion data objects to them.
 */
class PlotLayoutService
{
    /**
     * How close two plants have to be before their companion relationship is
     * worth mentioning. Beyond this they are sharing a bed on paper but not
     * really competing for the same soil.
     */
    public const NEIGHBOUR_CM = 120;

    /**
     * Every problem visible in a bed's current layout.
     *
     * @return array<int, array{kind: string, entries: array<int, int>, note: string}>
     */
    public function issues(GardenBed $bed): array
    {
        $placed = $bed->entries()
            ->with('plant')
            ->whereNotNull('x_cm')
            ->whereNotNull('y_cm')
            ->get();

        return [
            ...$this->outsideTheBed($bed, $placed),
            ...$this->pairIssues($placed),
        ];
    }

    /**
     * Plants whose footprint runs off the edge of the bed. Placing the centre
     * inside the bed is not enough: a pumpkin needs 90cm all to itself, so its
     * centre has to sit 45cm in from any edge.
     *
     * @param  Collection<int, GardenBedPlant>  $placed
     * @return array<int, array{kind: string, entries: array<int, int>, note: string}>
     */
    private function outsideTheBed(GardenBed $bed, Collection $placed): array
    {
        if (! $bed->hasPlot()) {
            return [];
        }

        $issues = [];

        foreach ($placed as $entry) {
            $radius = $this->radius($entry);

            $overhangs = $entry->x_cm - $radius < 0
                || $entry->y_cm - $radius < 0
                || $entry->x_cm + $radius > $bed->width_cm
                || $entry->y_cm + $radius > $bed->length_cm;

            if ($overhangs) {
                $issues[] = [
                    'kind' => 'overhang',
                    'entries' => [$entry->id],
                    'note' => "{$entry->plant->name} needs {$entry->plant->spacing_cm}cm of room and is too close to the edge.",
                ];
            }
        }

        return $issues;
    }

    /**
     * Crowding and bad-companion problems between pairs of placed plants.
     *
     * @param  Collection<int, GardenBedPlant>  $placed
     * @return array<int, array{kind: string, entries: array<int, int>, note: string}>
     */
    private function pairIssues(Collection $placed): array
    {
        $entries = $placed->values();
        $issues = [];
        $badCompanions = $this->badCompanionPairs($entries);

        foreach ($entries as $i => $a) {
            foreach ($entries as $j => $b) {
                if ($j <= $i) {
                    continue;
                }

                $distance = $this->distance($a, $b);
                $needed = $this->radius($a) + $this->radius($b);

                if ($needed > 0 && $distance < $needed) {
                    $gap = (int) round($distance);
                    $issues[] = [
                        'kind' => 'crowding',
                        'entries' => [$a->id, $b->id],
                        'note' => "{$a->plant->name} and {$b->plant->name} are {$gap}cm apart and want ".(int) round($needed).'cm.',
                    ];
                }

                $pair = $this->key($a->plant_id, $b->plant_id);

                if (isset($badCompanions[$pair]) && $distance <= self::NEIGHBOUR_CM) {
                    $issues[] = [
                        'kind' => 'companion',
                        'entries' => [$a->id, $b->id],
                        'note' => "{$a->plant->name} and {$b->plant->name} are poor companions and are planted close together.",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Every bad-companion pairing among the plants present, as a lookup.
     *
     * Loaded in one query for the whole bed rather than per pair: the naive
     * version asks the database the same question once for every pair of
     * plants on the plot, which is where a plan with thirty plants starts to
     * feel slow.
     *
     * @param  Collection<int, GardenBedPlant>  $entries
     * @return array<string, true>
     */
    private function badCompanionPairs(Collection $entries): array
    {
        $plantIds = $entries->pluck('plant_id')->unique()->values();

        if ($plantIds->count() < 2) {
            return [];
        }

        $rows = DB::table('plant_companions')
            ->where('relationship', 'bad')
            ->whereIn('plant_id', $plantIds)
            ->whereIn('companion_plant_id', $plantIds)
            ->get(['plant_id', 'companion_plant_id']);

        $pairs = [];

        foreach ($rows as $row) {
            $pairs[$this->key($row->plant_id, $row->companion_plant_id)] = true;
        }

        return $pairs;
    }

    private function key(int $a, int $b): string
    {
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    private function distance(GardenBedPlant $a, GardenBedPlant $b): float
    {
        return sqrt((($a->x_cm - $b->x_cm) ** 2) + (($a->y_cm - $b->y_cm) ** 2));
    }

    /**
     * Half the recommended spacing: the room this plant claims around its own
     * centre. Plants with no spacing on record claim nothing, so they never
     * trigger a crowding warning we cannot justify.
     */
    private function radius(GardenBedPlant $entry): float
    {
        return ($entry->plant?->spacing_cm ?? 0) / 2;
    }
}
