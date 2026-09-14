<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable([
    'seller_id', 'plant_id', 'title', 'slug', 'description', 'category',
    'stock', 'images', 'is_active',
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

    public function savers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'savable', 'saves')->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Stored as bare storage paths so the data doesn't bake in a hostname;
     * the URL is built per request instead.
     */
    public function imageUrls(): array
    {
        return array_map(
            fn (string $path) => str_starts_with($path, 'http') ? $path : url('/api/images/'.$path),
            $this->images ?? []
        );
    }

    /**
     * Reviews are limited to people who actually bought the product, so a
     * rating can't be left by someone who never received the thing.
     */
    public function wasPurchasedBy(User $user): bool
    {
        return OrderItem::where('product_id', $this->id)
            ->whereHas('order', fn ($q) => $q->where('buyer_id', $user->id))
            ->exists();
    }

    /**
     * The document shape indexed into Elasticsearch for this product.
     */
    public function toSearchArray(): array
    {
        $this->loadMissing('plant', 'seller');

        return [
            'rating_average' => $this->reviews()->avg('rating'),
            'reviews_count' => $this->reviews()->count(),
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category->value,
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
