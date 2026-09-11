<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued so a slow notification channel can never hold up checkout itself.
 */
class SendOrderConfirmation implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        $event->order->loadMissing('buyer');

        $event->order->buyer?->notify(new OrderStatusChanged($event->order));
    }
}
