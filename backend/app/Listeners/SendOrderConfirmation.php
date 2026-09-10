<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Stands in for a real email/notification send. Queued so a slow mail
 * provider can never hold up the checkout request itself.
 */
class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        Log::info('Order confirmation sent', [
            'order_id' => $event->order->id,
            'buyer_id' => $event->order->buyer_id,
            'total_pence' => $event->order->total_pence,
        ]);
    }
}
