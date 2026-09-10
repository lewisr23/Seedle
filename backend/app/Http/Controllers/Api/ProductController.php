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
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request, ProductSearchService $search): JsonResponse
    {
        $result = $search->search(
            filters: $request->only(['q', 'category', 'sun_requirement', 'zone', 'min_price', 'max_price', 'seller_id']),
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

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('seller', 'plant'));
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
