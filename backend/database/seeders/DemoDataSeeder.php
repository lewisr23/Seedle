<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PostType;
use App\Enums\ProductCategory;
use App\Models\Plant;
use App\Models\Product;
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

    private const SAVE_COUNT = 400;

    private const CONVERSATION_COUNT = 60;

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

        $this->command->info('Seeding saved items...');
        $this->seedSaves($userIds, $plantIds);

        $this->command->info('Seeding conversations...');
        $this->seedConversations($userIds);

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
        $products = DB::table('products')->inRandomOrder()->limit(500)->get(['id', 'seller_id']);

        if ($products->isEmpty()) {
            return;
        }

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $buyerId = $userIds[array_rand($userIds)];
            $itemCount = random_int(1, 4);
            $items = $products->random(min($itemCount, $products->count()));
            $items = $items instanceof Collection ? $items : collect([$items]);

            $orderId = DB::table('orders')->insertGetId([
                'buyer_id' => $buyerId,
                'status' => OrderStatus::Completed->value,
                'created_at' => $now->copy()->subDays(random_int(0, 200)),
                'updated_at' => $now,
            ]);

            $itemRows = [];
            foreach ($items as $product) {
                $qty = random_int(1, 3);
                $itemRows[] = [
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'quantity' => $qty,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('order_items')->insert($itemRows);
        }
    }

    /**
     * Saves span both savable types so the saved page has something in each
     * section. The demo account gets its own handful, since landing on an
     * empty page is a poor first impression of the feature.
     */
    private function seedSaves(array $userIds, array $plantIds): void
    {
        $now = now();
        $productIds = DB::table('products')->inRandomOrder()->limit(600)->pluck('id')->all();

        if ($productIds === [] || $plantIds === []) {
            return;
        }

        $rows = [];
        $seen = [];

        $add = function (int $userId, string $type, int $id) use (&$rows, &$seen, $now) {
            // The table is uniquely keyed on the three together, so the same
            // pair must not be generated twice in one batch.
            $key = $userId.'|'.$type.'|'.$id;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;

            $rows[] = [
                'user_id' => $userId,
                'savable_type' => $type,
                'savable_id' => $id,
                'created_at' => $now->copy()->subDays(random_int(0, 60)),
                'updated_at' => $now,
            ];
        };

        $demoUserId = DB::table('users')->where('email', 'test@example.com')->value('id');
        if ($demoUserId !== null) {
            foreach (array_slice($productIds, 0, 6) as $productId) {
                $add((int) $demoUserId, Product::class, (int) $productId);
            }
            foreach (array_slice($plantIds, 0, 4) as $plantId) {
                $add((int) $demoUserId, Plant::class, (int) $plantId);
            }
        }

        for ($i = 0; $i < self::SAVE_COUNT; $i++) {
            $userId = $userIds[array_rand($userIds)];

            if (random_int(1, 4) === 1) {
                $add($userId, Plant::class, $plantIds[array_rand($plantIds)]);
            } else {
                $add($userId, Product::class, $productIds[array_rand($productIds)]);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('saves')->insert($chunk);
        }
    }

    /**
     * Conversations are always buyer -> the listing's seller, matching what the
     * API enforces. The demo account gets a few with an unread reply waiting so
     * the inbox badge has something to show.
     */
    private function seedConversations(array $userIds): void
    {
        $now = now();
        $products = DB::table('products')->inRandomOrder()->limit(400)->get(['id', 'seller_id']);

        if ($products->isEmpty()) {
            return;
        }

        $openers = [
            'Hi, is this still available?',
            'Do you post to Scotland?',
            'How soon after delivery should I sow these?',
            'Would these do all right in a north-facing bed?',
            'Any chance of a discount if I take two?',
        ];
        $replies = [
            'Yes, still available - happy to post this week.',
            'It should be fine, just keep it out of the wind.',
            'I post everywhere in the UK, usually next-day.',
            'Sow them straight away for the best germination.',
            'I can do a bit off for two, yes.',
        ];

        $demoUserId = (int) DB::table('users')->where('email', 'test@example.com')->value('id');
        $pairs = [];
        $made = 0;

        foreach ($products as $index => $product) {
            if ($made >= self::CONVERSATION_COUNT) {
                break;
            }

            // First few belong to the demo account so its inbox isn't empty.
            $buyerId = $made < 3 && $demoUserId > 0 ? $demoUserId : $userIds[array_rand($userIds)];

            if ($buyerId === (int) $product->seller_id) {
                continue;
            }

            $key = $buyerId.'|'.$product->seller_id.'|'.$product->id;
            if (isset($pairs[$key])) {
                continue;
            }
            $pairs[$key] = true;

            $startedAt = $now->copy()->subDays(random_int(0, 30))->subHours(random_int(0, 23));
            $conversationId = DB::table('conversations')->insertGetId([
                'product_id' => $product->id,
                'buyer_id' => $buyerId,
                'seller_id' => $product->seller_id,
                'last_message_at' => $startedAt,
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);

            $messages = [[
                'conversation_id' => $conversationId,
                'sender_id' => $buyerId,
                'body' => $openers[array_rand($openers)],
                // The buyer's own message counts as read by them.
                'read_at' => $startedAt,
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]];

            $lastAt = $startedAt;

            // Most threads get an answer; leaving some unanswered is realistic.
            if (random_int(1, 5) > 1) {
                $lastAt = $startedAt->copy()->addHours(random_int(1, 20));
                $messages[] = [
                    'conversation_id' => $conversationId,
                    'sender_id' => $product->seller_id,
                    'body' => $replies[array_rand($replies)],
                    // Unread for the demo account, so the badge shows a count.
                    'read_at' => $buyerId === $demoUserId ? null : $lastAt,
                    'created_at' => $lastAt,
                    'updated_at' => $lastAt,
                ];
            }

            DB::table('messages')->insert($messages);
            DB::table('conversations')->where('id', $conversationId)->update(['last_message_at' => $lastAt]);
            $made++;
        }
    }
}
