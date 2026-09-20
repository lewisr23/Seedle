<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWantRequest;
use App\Http\Resources\WantResource;
use App\Models\Want;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $wants = Want::query()
            ->with(['user', 'plant'])
            // Closed requests stay readable on a profile but are clutter on
            // the browse page, so they are excluded unless asked for.
            ->when(! $request->boolean('include_closed'), fn ($q) => $q->open())
            ->when($request->filled('plant_id'), fn ($q) => $q->where('plant_id', $request->integer('plant_id')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest()
            ->paginate(20);

        return WantResource::collection($wants);
    }

    public function store(StoreWantRequest $request): WantResource
    {
        $want = $request->user()->wants()->create($request->validated());

        // refresh() so is_open reflects the column default rather than the
        // null it holds in memory straight after insert.
        return new WantResource($want->refresh()->load(['user', 'plant']));
    }

    /** Marking it filled is the normal end of a request, not deletion. */
    public function close(Request $request, Want $want): WantResource
    {
        $this->authorize('manage', $want);

        $want->update(['is_open' => false]);

        return new WantResource($want->load(['user', 'plant']));
    }

    public function destroy(Request $request, Want $want): JsonResponse
    {
        $this->authorize('manage', $want);

        $want->delete();

        return response()->json(['message' => 'Request removed.']);
    }
}
