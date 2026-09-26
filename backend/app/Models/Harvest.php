<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['garden_bed_plant_id', 'user_id', 'harvested_at', 'quantity', 'unit', 'notes'])]
class Harvest extends Model
{
    /** The units a harvest can be recorded in, smallest first. */
    public const UNITS = ['g', 'kg', 'count', 'bunch', 'punnet', 'trug'];

    protected function casts(): array
    {
        return [
            'harvested_at' => 'date',
            'quantity' => 'float',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(GardenBedPlant::class, 'garden_bed_plant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
