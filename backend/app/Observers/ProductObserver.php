<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Search\ProductSearchService;

class ProductObserver
{
    public function __construct(private readonly ProductSearchService $search) {}

    public function saved(Product $product): void
    {
        $this->search->indexProduct($product);
    }

    public function deleted(Product $product): void
    {
        $this->search->deleteProduct($product->id);
    }
}
