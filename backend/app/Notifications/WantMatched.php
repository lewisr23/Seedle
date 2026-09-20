<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Want;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WantMatched extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Want $want,
        public readonly Product $product,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'want_matched',
            'title' => 'Someone is offering '.($this->want->plant?->name ?? $this->want->title),
            'body' => $this->product->title.' has just been put up.',
            'want_id' => $this->want->id,
            'product_id' => $this->product->id,
            'link' => "/products/{$this->product->id}",
        ];
    }
}
