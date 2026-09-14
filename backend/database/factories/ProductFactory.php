<?php

namespace Database\Factories;

use App\Enums\ProductCategory;
use App\Models\Plant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    private const TOOL_NAMES = [
        'Stainless Steel Trowel', 'Bypass Secateurs', 'Long-Handled Fork', 'Watering Can (5L)',
        'Kneeling Pad', 'Garden Gloves', 'Soil pH Tester', 'Compost Bin', 'Rain Gauge', 'Dibber',
    ];

    public function definition(): array
    {
        $category = fake()->randomElement(ProductCategory::cases());
        $plant = in_array($category, [ProductCategory::Seed, ProductCategory::LivePlant], true)
            ? Plant::inRandomOrder()->first()
            : null;

        $title = match ($category) {
            ProductCategory::Seed => $plant ? "{$plant->name} Seeds" : 'Mixed Vegetable Seeds',
            ProductCategory::LivePlant => $plant ? "{$plant->name} Plug Plant" : 'Young Plant',
            ProductCategory::Tool => fake()->randomElement(self::TOOL_NAMES),
            ProductCategory::Fertilizer => fake()->randomElement(['Organic Compost 50L', 'Tomato Feed', 'Bone Meal', 'Liquid Seaweed Feed']),
            ProductCategory::Other => fake()->words(3, true),
        };

        return [
            // Always a fresh seller unless one is passed in, picking a random
            // existing user makes tests order-dependent (the buyer can end up
            // owning the product they're trying to act on).
            'seller_id' => User::factory(),
            'plant_id' => $plant?->id,
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(10000, 999999),
            'description' => fake()->sentence(20),
            'category' => $category,
            'stock' => fake()->numberBetween(0, 200),
            'images' => [],
            'is_active' => fake()->boolean(92),
        ];
    }
}
