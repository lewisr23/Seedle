<?php

namespace App\Observers;

use App\Models\Product;
use App\Notifications\BackInStock;
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

    public function deleted(Product $product): void
    {
        $this->search->deleteProduct($product->id);
    }
}
