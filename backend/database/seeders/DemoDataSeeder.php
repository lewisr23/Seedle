<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PostType;
use App\Enums\ProductCategory;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds a realistically-sized dataset (thousands of products/posts) using
 * chunked bulk inserts rather than one Eloquent ->create() per row, which
 * would be far slower and put unnecessary load on the database during seeding.
 */
class DemoDataSeeder extends Seeder
{
    private const USER_COUNT = 150;

    private const PRODUCT_COUNT = 3000;

    private const POST_COUNT = 900;

    private const FOLLOW_COUNT = 2500;

    private const ORDER_COUNT = 120;

    public function run(): void
    {
        $this->command->info('Seeding users...');
        User::factory()->count(self::USER_COUNT)->create();

        $userIds = User::pluck('id')->all();
        $plantIds = Plant::pluck('id')->all();

        $this->command->info('Seeding '.self::PRODUCT_COUNT.' products...');
        $this->seedProducts($userIds, $plantIds);

        $this->command->info('Seeding '.self::POST_COUNT.' posts...');
        $this->seedPosts($userIds, $plantIds);

        $this->command->info('Seeding follows...');
        $this->seedFollows($userIds);

        $this->command->info('Seeding likes and comments...');
        $this->seedEngagement($userIds);

        $this->command->info('Seeding '.self::ORDER_COUNT.' historical orders...');
        $this->seedOrders($userIds);

        $this->command->info('Done.');
    }

    private function seedProducts(array $userIds, array $plantIds): void
    {
        $categories = ProductCategory::cases();
        $now = now();
        $rows = [];

        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {
            $category = $categories[array_rand($categories)];
            $usesPlant = in_array($category, [ProductCategory::Seed, ProductCategory::LivePlant], true);
            $plantId = $usesPlant ? $plantIds[array_rand($plantIds)] : null;
            $title = 'Product '.Str::random(6).' '.$category->value;

            $rows[] = [
                'seller_id' => $userIds[array_rand($userIds)],
                'plant_id' => $plantId,
                'title' => $title,
                'slug' => Str::slug($title).'-'.$i,
                'description' => 'A quality '.$category->value.' listing for the garden marketplace.',
                'category' => $category->value,
                'price_pence' => random_int(150, 6000),
                'stock' => random_int(0, 250),
                'images' => json_encode([]),
                'is_active' => random_int(1, 100) <= 92 ? 1 : 0,
                'created_at' => $now->copy()->subDays(random_int(0, 365)),
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('products')->insert($rows);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table('products')->insert($rows);
        }
    }

    private function seedPosts(array $userIds, array $plantIds): void
    {
        $types = PostType::cases();
        $now = now();
        $rows = [];

        $bodies = [
            'Progress update on the plot this week.',
            'Finally got the beds weeded and mulched.',
            'Why are my leaves curling like this?',
            'Top tip: water in the evening during a heatwave to cut down on evaporation.',
            'First harvest of the season, very pleased with it.',
            'Anyone else struggling with slugs this year?',
            'Sowed a new batch of seeds indoors today.',
        ];

        for ($i = 0; $i < self::POST_COUNT; $i++) {
            $rows[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'plant_id' => random_int(1, 100) <= 60 ? $plantIds[array_rand($plantIds)] : null,
                'type' => $types[array_rand($types)]->value,
                'body' => $bodies[array_rand($bodies)],
                'image_path' => null,
                'created_at' => $now->copy()->subDays(random_int(0, 180)),
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('posts')->insert($rows);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table('posts')->insert($rows);
        }
    }

    private function seedFollows(array $userIds): void
    {
        $now = now();
        $seen = [];
        $rows = [];
        $attempts = 0;

        while (count($rows) < self::FOLLOW_COUNT && $attempts < self::FOLLOW_COUNT * 3) {
            $attempts++;
            $follower = $userIds[array_rand($userIds)];
            $followed = $userIds[array_rand($userIds)];

            if ($follower === $followed) {
                continue;
            }

            $key = $follower.':'.$followed;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $rows[] = [
                'follower_id' => $follower,
                'followed_id' => $followed,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('follows')->insert($rows);
                $rows = [];
            }
        }

        if ($rows) {
            DB::table('follows')->insert($rows);
        }
    }

    private function seedEngagement(array $userIds): void
    {
        $now = now();
        $postIds = DB::table('posts')->pluck('id')->all();

        $likeRows = [];
        $seenLikes = [];
        foreach ($postIds as $postId) {
            $likerCount = random_int(0, 8);
            $likers = (array) array_rand(array_flip($userIds), min($likerCount, count($userIds)) ?: 1);

            foreach ((array) $likers as $userId) {
                $key = $postId.':'.$userId;
                if (isset($seenLikes[$key]) || $likerCount === 0) {
                    continue;
                }
                $seenLikes[$key] = true;

                $likeRows[] = [
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (count($likeRows) >= 500) {
                DB::table('likes')->insert($likeRows);
                $likeRows = [];
            }
        }
        if ($likeRows) {
            DB::table('likes')->insert($likeRows);
        }

        $commentBodies = [
            'Great progress!',
            'Same thing happened to mine, try more mulch.',
            'Looks brilliant, well done.',
            'Could be blossom end rot, keep watering consistent.',
            'Thanks for the tip, trying this at the weekend.',
        ];
        $commentRows = [];
        foreach ($postIds as $postId) {
            if (random_int(1, 100) > 40) {
                continue;
            }
            $commentRows[] = [
                'post_id' => $postId,
                'user_id' => $userIds[array_rand($userIds)],
                'body' => $commentBodies[array_rand($commentBodies)],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($commentRows) >= 500) {
                DB::table('comments')->insert($commentRows);
                $commentRows = [];
            }
        }
        if ($commentRows) {
            DB::table('comments')->insert($commentRows);
        }
    }

    private function seedOrders(array $userIds): void
    {
        $now = now();
        $products = DB::table('products')->inRandomOrder()->limit(500)->get(['id', 'seller_id', 'price_pence']);

        if ($products->isEmpty()) {
            return;
        }

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $buyerId = $userIds[array_rand($userIds)];
            $itemCount = random_int(1, 4);
            $items = $products->random(min($itemCount, $products->count()));
            $items = $items instanceof Collection ? $items : collect([$items]);

            $total = 0;
            $orderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyerId,
                'status' => OrderStatus::Completed->value,
                'total_pence' => 0,
                'created_at' => $now->copy()->subDays(random_int(0, 200)),
                'updated_at' => $now,
            ]);

            $itemRows = [];
            foreach ($items as $product) {
                $qty = random_int(1, 3);
                $lineTotal = $qty * $product->price_pence;
                $total += $lineTotal;

                $itemRows[] = [
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'quantity' => $qty,
                    'unit_price_pence' => $product->price_pence,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('order_items')->insert($itemRows);
            DB::table('orders')->where('id', $orderId)->update(['total_pence' => $total]);
        }
    }
}
