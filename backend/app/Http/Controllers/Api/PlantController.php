<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantResource;
use App\Models\Plant;
use App\Services\Garden\GardenHelperService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Plant::query();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('sun_requirement')) {
            $query->where('sun_requirement', $request->string('sun_requirement'));
        }

        if ($request->filled('water_needs')) {
            $query->where('water_needs', $request->string('water_needs'));
        }

        // A plant "suits" a zone when that zone falls inside its hardiness range.
        if ($request->filled('zone')) {
            $zone = (int) $request->integer('zone');
            $query->where('min_zone', '<=', $zone)->where('max_zone', '>=', $zone);
        }

        if ($request->filled('month')) {
            $query->whereJsonContains('planting_months', (int) $request->integer('month'));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }

        return PlantResource::collection($query->orderBy('name')->paginate(60));
    }

    public function show(Plant $plant): PlantResource
    {
        $plant->load('goodCompanions', 'badCompanions');

        return new PlantResource($plant);
    }

    /**
     * "What can I plant right now?": filtered by the requester's hardiness
     * zone and (optionally) a month other than the current one.
     */
    public function recommendations(Request $request, GardenHelperService $helper): AnonymousResourceCollection
    {
        $request->validate([
            'zone' => ['required', 'integer', 'min:1', 'max:13'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $plants = $helper->recommendedPlants(
            zone: (int) $request->integer('zone'),
            month: $request->filled('month') ? (int) $request->integer('month') : null,
        );

        return PlantResource::collection($plants);
    }
}
