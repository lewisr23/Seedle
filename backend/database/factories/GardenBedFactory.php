<?php

namespace Database\Factories;

use App\Models\GardenBed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GardenBed>
 */
class GardenBedFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).' bed',
            'hardiness_zone' => (string) fake()->numberBetween(3, 10),
        ];
    }
}
