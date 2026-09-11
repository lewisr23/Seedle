<?php

namespace App\Models;

use App\Enums\GuideCategory;
use Database\Factories\GuideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'slug', 'excerpt', 'body', 'category', 'plant_id', 'read_minutes', 'published_at'])]
class Guide extends Model
{
    /** @use HasFactory<GuideFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => GuideCategory::class,
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
