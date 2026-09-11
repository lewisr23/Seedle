<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderStatusChanged;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Simulates the "slow" part of fulfilment (payment capture, warehouse
 * handoff, etc.) happening off the request/response cycle. Dispatched
 * with a short delay from CheckoutService so orders visibly move from
 * pending -> processing -> completed, demonstrating an async pipeline
 * a real payment/fulfilment integration would slot into.
 */
class CompleteOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $orderId) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->status !== OrderStatus::Processing) {
            return;
        }

        $order->update(['status' => OrderStatus::Completed]);

        $order->loadMissing('buyer');
        $order->buyer?->notify(new OrderStatusChanged($order));
    }
}
