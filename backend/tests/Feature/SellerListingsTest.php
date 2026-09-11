<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerListingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_listings_includes_inactive_and_out_of_stock_products(): void
    {
        $seller = User::factory()->create();
        Product::factory()->for($seller, 'seller')->create(['title' => 'Live Listing', 'is_active' => true, 'stock' => 4]);
        Product::factory()->for($seller, 'seller')->create(['title' => 'Paused Listing', 'is_active' => false, 'stock' => 0]);

        $response = $this->actingAs($seller, 'sanctum')->getJson('/api/my-listings');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Live Listing'));
        $this->assertTrue($titles->contains('Paused Listing'));
    }

    public function test_my_listings_only_returns_the_current_users_products(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        Product::factory()->for($seller, 'seller')->create(['title' => 'Mine']);
        Product::factory()->for($otherSeller, 'seller')->create(['title' => 'Theirs']);

        $response = $this->actingAs($seller, 'sanctum')->getJson('/api/my-listings');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Mine'));
        $this->assertFalse($titles->contains('Theirs'));
    }

    public function test_my_listings_requires_authentication(): void
    {
        $this->getJson('/api/my-listings')->assertStatus(401);
    }

    public function test_a_seller_can_update_their_own_listing(): void
    {
        $seller = User::factory()->create();
        $product = Product::factory()->for($seller, 'seller')->create(['price_pence' => 500, 'stock' => 2]);

        $response = $this->actingAs($seller, 'sanctum')->putJson("/api/products/{$product->id}", [
            'price_pence' => 750,
            'stock' => 9,
            'is_active' => false,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price_pence' => 750,
            'stock' => 9,
            'is_active' => false,
        ]);
    }

    public function test_a_seller_cannot_update_someone_elses_listing(): void
    {
        $seller = User::factory()->create();
        $intruder = User::factory()->create();
        $product = Product::factory()->for($seller, 'seller')->create(['price_pence' => 500]);

        $response = $this->actingAs($intruder, 'sanctum')->putJson("/api/products/{$product->id}", [
            'price_pence' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price_pence' => 500]);
    }

    public function test_sales_endpoint_returns_orders_containing_the_sellers_products(): void
    {
        $seller = User::factory()->create();
        $otherSeller = User::factory()->create();
        $buyer = User::factory()->create();

        $mine = Product::factory()->for($seller, 'seller')->create(['stock' => 10, 'price_pence' => 300]);
        $theirs = Product::factory()->for($otherSeller, 'seller')->create(['stock' => 10, 'price_pence' => 400]);

        $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [['product_id' => $mine->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [['product_id' => $theirs->id, 'quantity' => 1]],
        ])->assertCreated();

        $response = $this->actingAs($seller, 'sanctum')->getJson('/api/sales');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
