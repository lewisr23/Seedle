<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifySellersOfSale implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $itemsBySeller = $event->order->items->groupBy('seller_id');

        foreach ($itemsBySeller as $sellerId => $items) {
            Log::info('Seller notified of new sale', [
                'order_id' => $event->order->id,
                'seller_id' => $sellerId,
                'items' => $items->count(),
            ]);
        }
    }
}
