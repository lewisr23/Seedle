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

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }

        return PlantResource::collection($query->orderBy('name')->paginate(50));
    }

    public function show(Plant $plant): PlantResource
    {
        $plant->load('goodCompanions', 'badCompanions');

        return new PlantResource($plant);
    }

    /**
     * "What can I plant right now?" — filtered by the requester's hardiness
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
