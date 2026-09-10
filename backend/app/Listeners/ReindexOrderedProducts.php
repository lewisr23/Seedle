<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Services\Search\ProductSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Stock levels changed as part of the order, so the search index (which
 * holds a denormalised copy of stock) needs to catch up. Done async so
 * an Elasticsearch hiccup never blocks checkout.
 */
class ReindexOrderedProducts implements ShouldQueue
{
    public function __construct(private readonly ProductSearchService $search) {}

    public function handle(OrderPlaced $event): void
    {
        $event->order->items->loadMissing('product');

        foreach ($event->order->items as $item) {
            if ($item->product) {
                $this->search->indexProduct($item->product);
            }
        }
    }
}
