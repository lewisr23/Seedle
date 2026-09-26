<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHarvestRequest;
use App\Http\Resources\HarvestResource;
use App\Models\GardenBed;
use App\Models\Harvest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class HarvestController extends Controller
{
    /**
     * Everything this grower has picked, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $harvests = $request->user()->harvests()
            ->with(['entry.plant', 'entry.gardenBed'])
            ->when($request->filled('year'), fn ($q) => $q->whereYear('harvested_at', $request->integer('year')))
            ->when($request->filled('bed_id'), fn ($q) => $q->whereHas(
                'entry',
                fn ($e) => $e->where('garden_bed_id', $request->integer('bed_id'))
            ))
            ->orderByDesc('harvested_at')
            ->orderByDesc('id')
            ->paginate(30);

        return HarvestResource::collection($harvests);
    }

    /**
     * What the year came to.
     *
     * Grouped by plant *and* unit rather than plant alone, because two bunches
     * and two kilos are not four of anything. A plant weighed one week and
     * counted the next gets a line each, which is the honest answer.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = $request->integer('year') ?: (int) now()->year;

        $rows = $user->harvests()
            ->join('garden_bed_plants', 'harvests.garden_bed_plant_id', '=', 'garden_bed_plants.id')
            ->join('plants', 'garden_bed_plants.plant_id', '=', 'plants.id')
            ->whereYear('harvests.harvested_at', $year)
            ->groupBy('plants.id', 'plants.name', 'harvests.unit')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('plants.name')
            ->get([
                'plants.id as plant_id',
                'plants.name as plant_name',
                'harvests.unit',
                DB::raw('sum(harvests.quantity) as quantity'),
                DB::raw('count(*) as times'),
            ]);

        // Offered so the page can put a year switcher up without a second
        // request, and so it can land on a year that actually has something
        // in it rather than an empty current year.
        $years = $user->harvests()
            ->select(DB::raw('distinct '.$this->yearExpression().' as y'))
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->all();

        return response()->json([
            'year' => $year,
            'years' => $years,
            'totals' => $rows->map(fn ($row) => [
                'plant' => ['id' => (int) $row->plant_id, 'name' => $row->plant_name],
                'unit' => $row->unit,
                'quantity' => $row->quantity === null ? null : round((float) $row->quantity, 2),
                'times' => (int) $row->times,
            ]),
        ]);
    }

    public function store(StoreHarvestRequest $request, GardenBed $gardenBed, int $entry): JsonResponse
    {
        $this->authorize('update', $gardenBed);

        $row = $gardenBed->entries()->where('id', $entry)->firstOrFail();

        $harvest = $row->harvests()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json(
            new HarvestResource($harvest->load(['entry.plant', 'entry.gardenBed'])),
            201
        );
    }

    public function destroy(Request $request, Harvest $harvest): JsonResponse
    {
        abort_unless($harvest->user_id === $request->user()->id, 403);

        $harvest->delete();

        return response()->json(['message' => 'Harvest removed.']);
    }

    /**
     * Pulling the year out of a date, in whichever database is behind us.
     * SQLite has no YEAR(), and tests run on SQLite while production is MySQL.
     */
    private function yearExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "cast(strftime('%Y', harvested_at) as integer)"
            : 'year(harvested_at)';
    }
}
