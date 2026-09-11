<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function buy(User $buyer, Product $product, int $quantity = 1): void
    {
        $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ])->assertCreated();
    }

    public function test_a_buyer_can_review_a_product_they_purchased(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->buy($buyer, $product);

        $response = $this->actingAs($buyer, 'sanctum')->postJson("/api/products/{$product->id}/reviews", [
            'rating' => 5,
            'body' => 'Germinated in four days, very happy.',
        ]);

        $response->assertCreated()->assertJsonPath('data.rating', 5);
        $this->assertDatabaseHas('reviews', ['product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => 5]);
    }

    public function test_someone_who_never_bought_the_product_cannot_review_it(): void
    {
        $stranger = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs($stranger, 'sanctum')->postJson("/api/products/{$product->id}/reviews", [
            'rating' => 1,
            'body' => 'Never bought this.',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_a_seller_cannot_review_their_own_listing(): void
    {
        $seller = User::factory()->create();
        $product = Product::factory()->for($seller, 'seller')->create(['stock' => 5]);

        // Even having "bought" it, the seller is blocked.
        $this->buy($seller, $product);

        $response = $this->actingAs($seller, 'sanctum')->postJson("/api/products/{$product->id}/reviews", [
            'rating' => 5,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_reviewing_twice_updates_the_existing_review_rather_than_duplicating(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->buy($buyer, $product);

        $this->actingAs($buyer, 'sanctum')->postJson("/api/products/{$product->id}/reviews", ['rating' => 2]);
        $this->actingAs($buyer, 'sanctum')->postJson("/api/products/{$product->id}/reviews", ['rating' => 4]);

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', ['product_id' => $product->id, 'rating' => 4]);
    }

    public function test_rating_validation_rejects_out_of_range_scores(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->buy($buyer, $product);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    public function test_product_exposes_its_average_rating_and_review_count(): void
    {
        $product = Product::factory()->create(['stock' => 20]);

        foreach ([5, 4, 3] as $rating) {
            $buyer = User::factory()->create();
            $this->buy($buyer, $product);
            $this->actingAs($buyer, 'sanctum')->postJson("/api/products/{$product->id}/reviews", ['rating' => $rating]);
        }

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.reviews_count', 3);

        $this->assertEquals(4, $response->json('data.rating_average'));
    }

    public function test_reviews_can_be_listed_for_a_product(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->buy($buyer, $product);
        $this->actingAs($buyer, 'sanctum')->postJson("/api/products/{$product->id}/reviews", [
            'rating' => 4,
            'body' => 'Good quality seeds.',
        ]);

        $response = $this->getJson("/api/products/{$product->id}/reviews");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Good quality seeds.', $response->json('data.0.body'));
    }

    public function test_a_user_can_delete_their_own_review_but_not_someone_elses(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['stock' => 20]);

        $this->buy($buyer, $product);
        $reviewId = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 3])
            ->json('data.id');

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/products/{$product->id}/reviews/{$reviewId}")
            ->assertForbidden();

        $this->actingAs($buyer, 'sanctum')
            ->deleteJson("/api/products/{$product->id}/reviews/{$reviewId}")
            ->assertOk();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_products_can_be_sorted_by_rating(): void
    {
        $great = Product::factory()->create(['title' => 'Great Item', 'stock' => 20, 'is_active' => true]);
        $poor = Product::factory()->create(['title' => 'Poor Item', 'stock' => 20, 'is_active' => true]);

        $buyerA = User::factory()->create();
        $this->buy($buyerA, $great);
        $this->actingAs($buyerA, 'sanctum')->postJson("/api/products/{$great->id}/reviews", ['rating' => 5]);

        $buyerB = User::factory()->create();
        $this->buy($buyerB, $poor);
        $this->actingAs($buyerB, 'sanctum')->postJson("/api/products/{$poor->id}/reviews", ['rating' => 1]);

        $response = $this->getJson('/api/products?sort=rating_desc');

        $response->assertOk();
        $this->assertEquals('Great Item', collect($response->json('data'))->pluck('title')->first());
    }
}
