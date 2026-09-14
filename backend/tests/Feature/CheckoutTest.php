<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_an_order_and_decrements_stock(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ]);

        // The response reflects state immediately after the synchronous,
        // consistency-critical part of checkout, before the async
        // fulfilment job (queued, not awaited) marks it completed.
        $response->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Processing->value);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);

        // With QUEUE_CONNECTION=sync in tests, the queued job has already
        // run by the time the request returns.
        $this->assertDatabaseHas('orders', ['buyer_id' => $buyer->id, 'status' => OrderStatus::Completed->value]);
    }

    public function test_checkout_rejects_orders_that_exceed_available_stock(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);

        $response = $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(422);

        // Stock must be untouched. The whole order should have rolled back.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(401);
    }

    public function test_an_order_with_items_from_multiple_sellers_splits_correctly(): void
    {
        $buyer = User::factory()->create();
        $sellerA = User::factory()->create();
        $sellerB = User::factory()->create();
        $productA = Product::factory()->create(['seller_id' => $sellerA->id, 'stock' => 5]);
        $productB = Product::factory()->create(['seller_id' => $sellerB->id, 'stock' => 5]);

        $response = $this->actingAs($buyer, 'sanctum')->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1],
                ['product_id' => $productB->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'seller_id' => $sellerA->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'seller_id' => $sellerB->id]);
    }
}
