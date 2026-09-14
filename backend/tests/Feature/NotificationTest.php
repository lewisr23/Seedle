<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_following_a_user_notifies_them(): void
    {
        $follower = User::factory()->create(['username' => 'rosie']);
        $followed = User::factory()->create();

        $this->actingAs($follower, 'sanctum')->postJson("/api/users/{$followed->username}/follow")->assertOk();

        $this->assertCount(1, $followed->fresh()->notifications);
        $this->assertEquals('new_follower', $followed->fresh()->notifications->first()->data['type']);
    }

    public function test_following_the_same_user_twice_only_notifies_once(): void
    {
        $follower = User::factory()->create();
        $followed = User::factory()->create();

        $this->actingAs($follower, 'sanctum')->postJson("/api/users/{$followed->username}/follow");
        $this->actingAs($follower, 'sanctum')->postJson("/api/users/{$followed->username}/follow");

        $this->assertCount(1, $followed->fresh()->notifications);
    }

    public function test_commenting_notifies_the_post_author(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Looking good!'])
            ->assertCreated();

        $this->assertCount(1, $author->fresh()->notifications);
        $this->assertEquals('new_comment', $author->fresh()->notifications->first()->data['type']);
    }

    public function test_commenting_on_your_own_post_does_not_notify_you(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $this->actingAs($author, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Replying to myself'])
            ->assertCreated();

        $this->assertCount(0, $author->fresh()->notifications);
    }

    public function test_a_sale_notifies_the_seller_and_the_buyer(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $product = Product::factory()->for($seller, 'seller')->create(['stock' => 5]);

        $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated();

        $sellerNotifications = $seller->fresh()->notifications;
        $this->assertCount(1, $sellerNotifications);
        $this->assertEquals('new_sale', $sellerNotifications->first()->data['type']);
        $this->assertArrayNotHasKey('amount_pence', $sellerNotifications->first()->data);

        // Buyer gets one for the order being placed and one when it completes.
        $this->assertTrue($buyer->fresh()->notifications->count() >= 1);
        $this->assertEquals('order_status', $buyer->fresh()->notifications->first()->data['type']);
    }

    public function test_a_user_can_list_and_clear_their_notifications(): void
    {
        $follower = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($follower, 'sanctum')->postJson("/api/users/{$user->username}/follow");

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/notifications');

        $response->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertCount(1, $response->json('data'));

        $this->actingAs($user, 'sanctum')->postJson('/api/notifications/read-all')->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }
}
