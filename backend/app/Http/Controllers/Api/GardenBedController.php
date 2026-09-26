<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddGardenBedPlantRequest;
use App\Http\Requests\StoreGardenBedRequest;
use App\Http\Requests\UpdateGardenBedPlantRequest;
use App\Http\Resources\GardenBedPlantResource;
use App\Http\Resources\GardenBedResource;
use App\Http\Resources\PlantResource;
use App\Models\GardenBed;
use App\Models\Plant;
use App\Services\Garden\GardenHelperService;
use App\Services\Garden\PlotLayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GardenBedController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $beds = $request->user()->gardenBeds()->with('entries.plant')->get();

        return GardenBedResource::collection($beds);
    }

    public function store(StoreGardenBedRequest $request): GardenBedResource
    {
        $bed = $request->user()->gardenBeds()->create($request->validated());

        return new GardenBedResource($bed->load('entries.plant'));
    }

    public function show(GardenBed $gardenBed, PlotLayoutService $layout): GardenBedResource
    {
        $this->authorize('view', $gardenBed);

        $gardenBed->load(['entries.plant', 'entries.harvests']);

        return (new GardenBedResource($gardenBed))
            ->additional(['issues' => $layout->issues($gardenBed)]);
    }

    public function update(StoreGardenBedRequest $request, GardenBed $gardenBed): GardenBedResource
    {
        $this->authorize('update', $gardenBed);

        $gardenBed->update($request->validated());

        return new GardenBedResource($gardenBed->load('entries.plant'));
    }

    public function destroy(Request $request, GardenBed $gardenBed): JsonResponse
    {
        $this->authorize('delete', $gardenBed);

        $gardenBed->delete();

        return response()->json(['message' => 'Garden bed deleted.']);
    }

    public function addPlant(AddGardenBedPlantRequest $request, GardenBed $gardenBed, GardenHelperService $helper, PlotLayoutService $layout): JsonResponse
    {
        $this->authorize('update', $gardenBed);

        $plant = Plant::findOrFail($request->validated()['plant_id']);
        $warnings = $helper->checkNewPlantAgainstBed($gardenBed, $plant);

        $entry = $gardenBed->entries()->create($request->validated());

        return response()->json([
            'entry' => new GardenBedPlantResource($entry->load('plant')),
            'warnings' => collect($warnings)->map(fn ($w) => [
                'plant' => new PlantResource($w['plant']),
                'note' => $w['note'],
            ]),
            'issues' => $layout->issues($gardenBed->fresh()),
        ], 201);
    }

    /**
     * Move a plant around the plan, or edit when it went in and its notes.
     *
     * This is what a drag on the planner calls, so it answers with the freshly
     * recalculated layout issues: dragging a courgette next to a potato should
     * say so the moment you let go, not on the next page load.
     */
    public function updatePlant(UpdateGardenBedPlantRequest $request, GardenBed $gardenBed, int $entry, PlotLayoutService $layout): JsonResponse
    {
        $this->authorize('update', $gardenBed);

        $row = $gardenBed->entries()->where('id', $entry)->firstOrFail();
        $row->update($request->validated());

        return response()->json([
            'entry' => new GardenBedPlantResource($row->load('plant')),
            'issues' => $layout->issues($gardenBed->fresh()),
        ]);
    }

    public function removePlant(Request $request, GardenBed $gardenBed, int $entry): JsonResponse
    {
        $this->authorize('update', $gardenBed);

        $gardenBed->entries()->where('id', $entry)->firstOrFail()->delete();

        return response()->json(['message' => 'Removed from bed.']);
    }
}
