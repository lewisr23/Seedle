<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'seller_id', 'plant_id', 'title', 'slug', 'description', 'category',
    'price_pence', 'stock', 'images', 'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'images' => 'array',
            'is_active' => 'boolean',
            'price_pence' => 'integer',
            'stock' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function priceInPounds(): float
    {
        return round($this->price_pence / 100, 2);
    }

    /**
     * The document shape indexed into Elasticsearch for this product.
     */
    public function toSearchArray(): array
    {
        $this->loadMissing('plant', 'seller');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category->value,
            'price_pence' => $this->price_pence,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'seller_id' => $this->seller_id,
            'seller_username' => $this->seller?->username,
            'plant_id' => $this->plant_id,
            'sun_requirement' => $this->plant?->sun_requirement?->value,
            'min_zone' => $this->plant?->min_zone,
            'max_zone' => $this->plant?->max_zone,
            'plant_type' => $this->plant?->type?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
