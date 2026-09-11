<?php

namespace Database\Factories;

use App\Enums\GuideCategory;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Guide>
 */
class GuideFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraphs(3, true),
            'category' => fake()->randomElement(GuideCategory::cases())->value,
            'plant_id' => null,
            'read_minutes' => fake()->numberBetween(2, 6),
            'published_at' => now(),
        ];
    }
}
