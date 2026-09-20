<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\Want;
use App\Notifications\BackInStock;
use App\Notifications\WantMatched;
use App\Services\Search\ProductSearchService;

class ProductObserver
{
    public function __construct(private readonly ProductSearchService $search) {}

    public function saved(Product $product): void
    {
        $this->search->indexProduct($product);
    }

    /**
     * Only the 0 -> positive transition counts as a restock. Topping up stock
     * that never ran out isn't news, and firing on every save would notify on
     * each sale that decremented it.
     */
    public function updated(Product $product): void
    {
        if (! $product->wasChanged('stock') || (int) $product->getOriginal('stock') !== 0) {
            return;
        }

        if ($product->stock < 1 || ! $product->is_active) {
            return;
        }

        $product->savers()->each(fn ($user) => $user->notify(new BackInStock($product)));
    }

    /**
     * A new listing can answer an open request. Matched on the plant rather
     * than the title, so "toms" and "Tomato" still find each other, and only
     * when the listing is actually available to claim.
     */
    public function created(Product $product): void
    {
        if ($product->plant_id === null || ! $product->is_active || $product->stock < 1) {
            return;
        }

        Want::query()
            ->open()
            ->where('plant_id', $product->plant_id)
            ->where('user_id', '!=', $product->seller_id)
            ->with('user', 'plant')
            ->each(fn (Want $want) => $want->user->notify(new WantMatched($want, $product)));
    }

    public function deleted(Product $product): void
    {
        $this->search->deleteProduct($product->id);
    }
}
