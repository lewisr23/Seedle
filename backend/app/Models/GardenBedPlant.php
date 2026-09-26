<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['garden_bed_id', 'plant_id', 'x_cm', 'y_cm', 'planted_at', 'notes'])]
class GardenBedPlant extends Model
{
    protected function casts(): array
    {
        return [
            'planted_at' => 'date',
            'x_cm' => 'integer',
            'y_cm' => 'integer',
        ];
    }

    public function gardenBed(): BelongsTo
    {
        return $this->belongsTo(GardenBed::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function harvests(): HasMany
    {
        return $this->hasMany(Harvest::class);
    }

    /**
     * Whether this entry has been given a spot on the bed's plan.
     */
    public function isPlaced(): bool
    {
        return $this->x_cm !== null && $this->y_cm !== null;
    }
}
