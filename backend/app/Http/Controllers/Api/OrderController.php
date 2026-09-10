<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function checkout(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        try {
            $order = $checkout->placeOrder($request->user(), $request->validated()['items']);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with('items.product', 'items.seller')
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $isBuyer = $order->buyer_id === $request->user()->id;
        $isSeller = $order->items()->where('seller_id', $request->user()->id)->exists();

        if (! $isBuyer && ! $isSeller) {
            abort(403);
        }

        return new OrderResource($order->load('buyer', 'items.product', 'items.seller'));
    }

    /**
     * Orders containing at least one of the current user's product listings.
     */
    public function sales(Request $request): AnonymousResourceCollection
    {
        $orders = Order::whereHas('items', fn ($q) => $q->where('seller_id', $request->user()->id))
            ->with(['items' => fn ($q) => $q->where('seller_id', $request->user()->id), 'items.product', 'buyer'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }
}
