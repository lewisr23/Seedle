<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        $reviews = $product->reviews()->with('user')->latest()->paginate(20);

        return ReviewResource::collection($reviews);
    }

    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        $user = $request->user();

        if ($product->seller_id === $user->id) {
            return response()->json(['message' => "You can't review your own listing."], 422);
        }

        if (! $product->wasPurchasedBy($user)) {
            return response()->json(['message' => 'Only buyers of this item can review it.'], 403);
        }

        $review = $product->reviews()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated(),
        );

        return (new ReviewResource($review->load('user')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Product $product, int $review): JsonResponse
    {
        $existing = $product->reviews()->where('id', $review)->firstOrFail();

        if ($existing->user_id !== $request->user()->id) {
            abort(403);
        }

        $existing->delete();

        return response()->json(['message' => 'Review removed.']);
    }
}
