<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Models\User;
use App\Notifications\NewSale;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifySellersOfSale implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $event->order->loadMissing('items.product');

        foreach ($event->order->items->groupBy('seller_id') as $sellerId => $items) {
            $seller = User::find($sellerId);

            if (! $seller) {
                continue;
            }

            $seller->notify(new NewSale($event->order, $items));
        }
    }
}
