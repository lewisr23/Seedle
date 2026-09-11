<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddGardenBedPlantRequest;
use App\Http\Requests\StoreGardenBedRequest;
use App\Http\Resources\GardenBedResource;
use App\Http\Resources\PlantResource;
use App\Models\GardenBed;
use App\Models\Plant;
use App\Services\Garden\GardenHelperService;
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

    public function show(GardenBed $gardenBed): GardenBedResource
    {
        $this->authorize('view', $gardenBed);

        return new GardenBedResource($gardenBed->load('entries.plant'));
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

    public function addPlant(AddGardenBedPlantRequest $request, GardenBed $gardenBed, GardenHelperService $helper): JsonResponse
    {
        $this->authorize('update', $gardenBed);

        $plant = Plant::findOrFail($request->validated()['plant_id']);
        $warnings = $helper->checkNewPlantAgainstBed($gardenBed, $plant);

        $entry = $gardenBed->entries()->create($request->validated());

        return response()->json([
            'entry' => [
                'entry_id' => $entry->id,
                'plant' => new PlantResource($plant),
                'planted_at' => $entry->planted_at,
                'notes' => $entry->notes,
            ],
            'warnings' => collect($warnings)->map(fn ($w) => [
                'plant' => new PlantResource($w['plant']),
                'note' => $w['note'],
            ]),
        ], 201);
    }

    public function removePlant(Request $request, GardenBed $gardenBed, int $entry): JsonResponse
    {
        $this->authorize('update', $gardenBed);

        $gardenBed->entries()->where('id', $entry)->firstOrFail()->delete();

        return response()->json(['message' => 'Removed from bed.']);
    }
}
