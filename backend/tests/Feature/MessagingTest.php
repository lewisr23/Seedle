<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private function listing(User $seller): Product
    {
        return Product::factory()->create(['seller_id' => $seller->id]);
    }

    public function test_a_buyer_can_start_a_conversation_about_a_listing(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $product = $this->listing($seller);

        $response = $this->actingAs($buyer, 'sanctum')->postJson('/api/conversations', [
            'product_id' => $product->id,
            'body' => 'Is this still available?',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('conversations', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('messages', [
            'sender_id' => $buyer->id,
            'body' => 'Is this still available?',
        ]);
    }

    public function test_messaging_the_same_listing_twice_continues_one_thread(): void
    {
        $buyer = User::factory()->create();
        $product = $this->listing(User::factory()->create());

        // 201 the first time because the conversation is created; 200 the
        // second because the message joins the thread that already exists.
        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => 'First'])
            ->assertCreated();
        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => 'Second'])
            ->assertOk();

        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, Message::count());
    }

    public function test_a_seller_cannot_message_themselves_about_their_own_listing(): void
    {
        $seller = User::factory()->create();
        $product = $this->listing($seller);

        $this->actingAs($seller, 'sanctum')
            ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => 'Hello me'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('product_id');

        $this->assertSame(0, Conversation::count());
    }

    public function test_starting_a_conversation_notifies_the_seller_only(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $product = $this->listing($seller);

        $this->actingAs($buyer, 'sanctum')->postJson('/api/conversations', [
            'product_id' => $product->id,
            'body' => 'Do you post to Scotland?',
        ])->assertCreated();

        $this->assertCount(1, $seller->fresh()->notifications);
        $this->assertSame('new_message', $seller->fresh()->notifications->first()->data['type']);
        // The sender should never be told about their own message.
        $this->assertCount(0, $buyer->fresh()->notifications);
    }

    public function test_both_sides_can_reply_and_the_other_party_is_notified(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();
        $product = $this->listing($seller);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => 'Hi'])
            ->assertCreated();
        $conversation = Conversation::first();

        $this->actingAs($seller, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Yes, still available'])
            ->assertCreated();

        $this->assertSame(2, Message::count());
        $this->assertCount(1, $buyer->fresh()->notifications);
    }

    public function test_an_outsider_cannot_read_or_reply_to_a_conversation(): void
    {
        $buyer = User::factory()->create();
        $product = $this->listing(User::factory()->create());
        $nosy = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => 'Private'])
            ->assertCreated();
        $conversation = Conversation::first();

        $this->actingAs($nosy, 'sanctum')
            ->getJson("/api/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->actingAs($nosy, 'sanctum')
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'Butting in'])
            ->assertForbidden();

        $this->assertSame(1, Message::count());
    }

    public function test_conversations_require_authentication(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
        $this->postJson('/api/conversations', [])->assertUnauthorized();
    }

    public function test_the_inbox_lists_both_sides_newest_first_with_unread_counts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // One where the user is the buyer...
        $this->actingAs($user, 'sanctum')->postJson('/api/conversations', [
            'product_id' => $this->listing($other)->id,
            'body' => 'Buying question',
        ])->assertCreated();

        // ...and one where they are the seller, with an unread message waiting.
        // Separated in time so "newest first" is actually testable rather than
        // two rows sharing a timestamp.
        $this->travel(5)->minutes();
        $this->actingAs($other, 'sanctum')->postJson('/api/conversations', [
            'product_id' => $this->listing($user)->id,
            'body' => 'Selling question',
        ])->assertCreated();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/conversations')->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Newest conversation first.
        $this->assertSame($other->username, $data[0]['counterpart']['username']);
        $this->assertSame(1, $data[0]['unread_count']);
        // The one the user started themselves has nothing unread.
        $this->assertSame(0, $data[1]['unread_count']);
    }

    public function test_opening_a_conversation_marks_the_other_sides_messages_read(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')->postJson('/api/conversations', [
            'product_id' => $this->listing($seller)->id,
            'body' => 'Question',
        ])->assertCreated();
        $conversation = Conversation::first();

        $this->assertSame(1, $conversation->unreadCountFor($seller));

        $this->actingAs($seller, 'sanctum')
            ->getJson("/api/conversations/{$conversation->id}")
            ->assertOk();

        $this->assertSame(0, $conversation->fresh()->unreadCountFor($seller));
        // Reading someone else's message must not mark your own as read.
        $this->assertSame(0, $conversation->fresh()->unreadCountFor($buyer));
    }

    public function test_unread_count_endpoint_counts_across_conversations(): void
    {
        $seller = User::factory()->create();
        $product = $this->listing($seller);

        foreach (['One', 'Two'] as $body) {
            $this->actingAs(User::factory()->create(), 'sanctum')
                ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => $body])
                ->assertCreated();
        }

        $this->actingAs($seller, 'sanctum')
            ->getJson('/api/conversations/unread-count')
            ->assertOk()
            ->assertJson(['unread_count' => 2]);
    }

    public function test_a_message_body_is_required_and_bounded(): void
    {
        $buyer = User::factory()->create();
        $product = $this->listing(User::factory()->create());

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations', ['product_id' => $product->id, 'body' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/conversations', [
                'product_id' => $product->id,
                'body' => str_repeat('a', 2001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');
    }
}
