<?php

namespace Database\Factories;

use App\Enums\PostType;
use App\Models\Plant;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    private const QUESTIONS = [
        'Why are my tomato leaves turning yellow at the bottom?',
        'Is it too late to sow carrots this month?',
        'My courgette flowers are dropping off without fruiting: normal?',
        'Best way to keep slugs off my lettuce without chemicals?',
        'Can I plant strawberries in a container this small?',
    ];

    private const TIPS = [
        'Water tomatoes at the base, not the leaves, to help avoid blight.',
        'Pinch out basil flower buds to keep the leaves coming.',
        'Earth up potatoes every couple of weeks once shoots appear.',
        'A layer of mulch cuts down on watering and weeding at the same time.',
        'Sow little and often with lettuce so it doesn\'t all mature at once.',
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(PostType::cases());

        $body = match ($type) {
            PostType::Question => fake()->randomElement(self::QUESTIONS),
            PostType::Tip => fake()->randomElement(self::TIPS),
            PostType::Update => fake()->sentence(fake()->numberBetween(8, 20)),
        };

        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'plant_id' => fake()->optional(0.6)->randomElement(Plant::pluck('id')->all()),
            'type' => $type,
            'body' => $body,
            'image_path' => null,
        ];
    }
}
