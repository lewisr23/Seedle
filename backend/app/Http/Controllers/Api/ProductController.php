<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Search\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request, ProductSearchService $search): JsonResponse
    {
        $filters = $request->only(['q', 'category', 'sun_requirement', 'zone', 'min_price', 'max_price', 'seller_id', 'plant_id', 'sort']);
        $filters['in_stock'] = $request->boolean('in_stock');

        $result = $search->search(
            filters: $filters,
            page: (int) $request->integer('page', 1),
            perPage: min((int) $request->integer('per_page', 20), 50),
        );

        return response()->json([
            'source' => $result['source'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'facets' => $result['facets'],
            'data' => ProductResource::collection($result['results']),
        ]);
    }

    /**
     * The current user's own listings, unlike the public search this includes
     * deactivated and out-of-stock ones, since a seller still needs to manage them.
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $products = $request->user()->products()
            ->with('plant')
            ->latest()
            ->paginate(30);

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product->load('seller', 'plant')->loadAvg('reviews', 'rating')->loadCount('reviews');

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $product = $request->user()->products()->create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('title')).'-'.uniqid(),
        ]);

        return new ProductResource($product->load('seller', 'plant'));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return new ProductResource($product->load('seller', 'plant'));
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['message' => 'Listing removed.']);
    }
}
