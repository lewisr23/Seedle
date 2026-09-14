<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Exceptions\InsufficientStockException;
use App\Jobs\CompleteOrderJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     *
     * @throws InsufficientStockException
     */
    public function placeOrder(User $buyer, array $items): Order
    {
        $order = DB::transaction(function () use ($buyer, $items) {
            $order = Order::create([
                'buyer_id' => $buyer->id,
                'status' => OrderStatus::Pending,
            ]);

            foreach ($items as $item) {
                // Lock the row so two simultaneous claims can't both take
                // the last unit. No money changes hands, but the race is the
                // same one a shop has.
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->firstOrFail();

                if ($product->stock < $item['quantity']) {
                    throw new InsufficientStockException($product, $item['quantity']);
                }

                $product->decrement('stock', $item['quantity']);

                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'quantity' => $item['quantity'],
                ]);
            }

            $order->update(['status' => OrderStatus::Processing]);

            $order->load('items.product', 'items.seller');

            return $order;
        });

        // Dispatched only once the transaction has committed, so a queue
        // worker can never pick these up before the order actually exists
        // in the database (the classic "commit vs. dispatch" race).
        OrderPlaced::dispatch($order);
        CompleteOrderJob::dispatch($order->id)->delay(now()->addSeconds(5));

        return $order;
    }
}
