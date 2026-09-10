<?php

namespace App\Models;

use App\Enums\PlantType;
use App\Enums\SunRequirement;
use App\Enums\WaterNeeds;
use Database\Factories\PlantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'scientific_name', 'slug', 'type', 'sun_requirement', 'water_needs',
    'soil_type', 'min_zone', 'max_zone', 'days_to_maturity', 'planting_months', 'description',
])]
class Plant extends Model
{
    /** @use HasFactory<PlantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PlantType::class,
            'sun_requirement' => SunRequirement::class,
            'water_needs' => WaterNeeds::class,
            'planting_months' => 'array',
            'min_zone' => 'integer',
            'max_zone' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function goodCompanions(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'plant_companions', 'plant_id', 'companion_plant_id')
            ->wherePivot('relationship', 'good');
    }

    public function badCompanions(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'plant_companions', 'plant_id', 'companion_plant_id')
            ->wherePivot('relationship', 'bad');
    }

    /**
     * Whether this plant is suitable to grow in the given USDA hardiness zone.
     */
    public function suitableForZone(int $zone): bool
    {
        return $zone >= $this->min_zone && $zone <= $this->max_zone;
    }

    /**
     * Whether this plant is normally planted in the given calendar month (1-12).
     */
    public function plantableInMonth(int $month): bool
    {
        return in_array($month, $this->planting_months, true);
    }
}
