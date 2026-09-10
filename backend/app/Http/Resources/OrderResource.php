<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'total_pence' => $this->total_pence,
            'total_pounds' => round($this->total_pence / 100, 2),
            'buyer' => new UserResource($this->whenLoaded('buyer')),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'unit_price_pence' => $item->unit_price_pence,
                'product' => new ProductResource($item->product),
                'seller' => new UserResource($item->seller),
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
