<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlantResource;
use App\Http\Resources\ProductResource;
use App\Models\Plant;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $products = $user->savedProducts()
            ->with(['seller', 'plant'])
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->withCount('reviews')
            ->orderByDesc('saves.created_at')
            ->get();

        $plants = $user->savedPlants()
            ->orderByDesc('saves.created_at')
            ->get();

        return response()->json([
            'products' => ProductResource::collection($products),
            'plants' => PlantResource::collection($plants),
        ]);
    }

    /**
     * Just the ids, so a heart toggle anywhere in the UI can render its state
     * from one small request instead of a per-card lookup.
     */
    public function ids(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'product_ids' => $user->savedProducts()->pluck('products.id'),
            'plant_ids' => $user->savedPlants()->pluck('plants.id'),
        ]);
    }

    public function saveProduct(Request $request, Product $product): JsonResponse
    {
        // syncWithoutDetaching rather than attach: saving twice is idempotent
        // rather than a unique-constraint violation.
        $request->user()->savedProducts()->syncWithoutDetaching([$product->id]);

        return response()->json(['saved' => true]);
    }

    public function unsaveProduct(Request $request, Product $product): JsonResponse
    {
        $request->user()->savedProducts()->detach($product->id);

        return response()->json(['saved' => false]);
    }

    public function savePlant(Request $request, Plant $plant): JsonResponse
    {
        $request->user()->savedPlants()->syncWithoutDetaching([$plant->id]);

        return response()->json(['saved' => true]);
    }

    public function unsavePlant(Request $request, Plant $plant): JsonResponse
    {
        $request->user()->savedPlants()->detach($plant->id);

        return response()->json(['saved' => false]);
    }
}
