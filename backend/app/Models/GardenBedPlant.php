<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['garden_bed_id', 'plant_id', 'planted_at', 'notes'])]
class GardenBedPlant extends Model
{
    protected function casts(): array
    {
        return [
            'planted_at' => 'date',
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
}
