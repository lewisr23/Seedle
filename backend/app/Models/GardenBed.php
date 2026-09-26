<?php

namespace App\Models;

use Database\Factories\GardenBedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'hardiness_zone', 'width_cm', 'length_cm'])]
class GardenBed extends Model
{
    /** @use HasFactory<GardenBedFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GardenBedPlant::class);
    }

    /**
     * Whether this bed has been given a size, and so can be drawn to scale.
     */
    public function hasPlot(): bool
    {
        return $this->width_cm !== null && $this->length_cm !== null;
    }
}
