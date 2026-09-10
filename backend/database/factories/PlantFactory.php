<?php

namespace Database\Factories;

use App\Enums\PlantType;
use App\Enums\SunRequirement;
use App\Enums\WaterNeeds;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $minZone = fake()->numberBetween(2, 8);

        return [
            'name' => Str::title($name),
            'scientific_name' => fake()->optional()->words(2, true),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'type' => fake()->randomElement(PlantType::cases()),
            'sun_requirement' => fake()->randomElement(SunRequirement::cases()),
            'water_needs' => fake()->randomElement(WaterNeeds::cases()),
            'soil_type' => fake()->randomElement(['loamy', 'sandy', 'clay', 'chalky', 'well-drained']),
            'min_zone' => $minZone,
            'max_zone' => $minZone + fake()->numberBetween(2, 6),
            'days_to_maturity' => fake()->numberBetween(30, 120),
            'planting_months' => fake()->randomElements(range(1, 12), fake()->numberBetween(1, 3)),
            'description' => fake()->sentence(15),
        ];
    }
}
