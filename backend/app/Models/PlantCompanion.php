<?php

namespace App\Models;

use App\Enums\CompanionRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['plant_id', 'companion_plant_id', 'relationship', 'note'])]
class PlantCompanion extends Model
{
    protected function casts(): array
    {
        return [
            'relationship' => CompanionRelationship::class,
        ];
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function companionPlant()
    {
        return $this->belongsTo(Plant::class, 'companion_plant_id');
    }
}
