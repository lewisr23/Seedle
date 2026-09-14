<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status',
            'title' => 'Order #'.$this->order->id.' '.$this->order->status->value,
            'body' => 'Your order is now '.$this->order->status->value.'.',
            'order_id' => $this->order->id,
            'link' => '/orders',
        ];
    }
}
