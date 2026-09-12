<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BackInStock extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Product $product) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'back_in_stock',
            'title' => 'Back in stock',
            'body' => $this->product->title.' is available again.',
            'product_id' => $this->product->id,
            'link' => "/products/{$this->product->id}",
        ];
    }
}
