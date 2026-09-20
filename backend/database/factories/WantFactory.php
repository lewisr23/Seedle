<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Want;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Want> */
class WantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plant_id' => null,
            'title' => 'Looking for '.fake()->word(),
            'description' => fake()->sentence(),
            'is_open' => true,
        ];
    }
}
