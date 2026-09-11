<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GuideResource;
use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuideController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Guide::query()->published()->with('plant')->latest('published_at');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('excerpt', 'like', $term));
        }

        return GuideResource::collection($query->paginate(20));
    }

    public function show(Guide $guide): GuideResource
    {
        return new GuideResource($guide->load('plant'));
    }
}
