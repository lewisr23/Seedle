<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NewSale extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    public function __construct(
        public readonly Order $order,
        public readonly Collection $items,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $total = $this->items->sum(fn ($item) => $item->unit_price_pence * $item->quantity);
        $first = $this->items->first();
        $extra = $this->items->count() - 1;

        return [
            'type' => 'new_sale',
            'title' => 'You made a sale',
            'body' => $extra > 0
                ? "{$first->quantity} × {$first->product?->title} and {$extra} more item(s)"
                : "{$first->quantity} × {$first->product?->title}",
            'amount_pence' => $total,
            'order_id' => $this->order->id,
            'link' => '/dashboard',
        ];
    }
}
