<?php

namespace App\Exceptions;

use App\Models\Product;
use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(public readonly Product $product, public readonly int $requested)
    {
        parent::__construct(
            "Only {$product->stock} of \"{$product->title}\" left in stock (requested {$requested})."
        );
    }
}
