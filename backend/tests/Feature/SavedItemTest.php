<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_save_and_unsave_a_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/products/{$product->id}/save")
            ->assertOk()
            ->assertJson(['saved' => true]);

        $this->assertSame(1, $user->savedProducts()->count());

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/products/{$product->id}/save")
            ->assertOk()
            ->assertJson(['saved' => false]);

        $this->assertSame(0, $user->savedProducts()->count());
    }

    public function test_saving_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/products/{$product->id}/save")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/products/{$product->id}/save")->assertOk();

        $this->assertSame(1, $user->savedProducts()->count());
    }

    public function test_a_user_can_save_a_plant(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/plants/{$plant->id}/save")
            ->assertOk();

        $this->assertSame(1, $user->savedPlants()->count());
        // Products and plants share a table but must not bleed into each other.
        $this->assertSame(0, $user->savedProducts()->count());
    }

    public function test_the_saved_page_returns_both_kinds_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Product::factory()->create();
        $newer = Product::factory()->create();
        $plant = Plant::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/products/{$older->id}/save")->assertOk();
        $this->travel(2)->minutes();
        $this->actingAs($user, 'sanctum')->postJson("/api/products/{$newer->id}/save")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/plants/{$plant->id}/save")->assertOk();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/saved')->assertOk();

        $this->assertCount(2, $response->json('products'));
        $this->assertCount(1, $response->json('plants'));
        $this->assertSame($newer->id, $response->json('products.0.id'));
    }

    public function test_the_ids_endpoint_returns_only_your_own_saves(): void
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();
        $mine = Product::factory()->create();
        $theirs = Product::factory()->create();
        $plant = Plant::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/products/{$mine->id}/save")->assertOk();
        $this->actingAs($user, 'sanctum')->postJson("/api/plants/{$plant->id}/save")->assertOk();
        $this->actingAs($someoneElse, 'sanctum')->postJson("/api/products/{$theirs->id}/save")->assertOk();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/saved/ids')->assertOk();

        $this->assertSame([$mine->id], $response->json('product_ids'));
        $this->assertSame([$plant->id], $response->json('plant_ids'));
    }

    public function test_saving_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->id}/save")->assertUnauthorized();
        $this->getJson('/api/saved')->assertUnauthorized();
    }

    public function test_restocking_notifies_everyone_who_saved_the_listing(): void
    {
        $watcher = User::factory()->create();
        $bystander = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0]);

        $this->actingAs($watcher, 'sanctum')->postJson("/api/products/{$product->id}/save")->assertOk();

        $product->update(['stock' => 5]);

        $this->assertCount(1, $watcher->fresh()->notifications);
        $this->assertSame('back_in_stock', $watcher->fresh()->notifications->first()->data['type']);
        $this->assertCount(0, $bystander->fresh()->notifications);
    }

    public function test_topping_up_stock_that_never_ran_out_notifies_nobody(): void
    {
        $watcher = User::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAs($watcher, 'sanctum')->postJson("/api/products/{$product->id}/save")->assertOk();

        $product->update(['stock' => 10]);

        $this->assertCount(0, $watcher->fresh()->notifications);
    }

    public function test_selling_the_last_unit_does_not_notify(): void
    {
        $watcher = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);

        $this->actingAs($watcher, 'sanctum')->postJson("/api/products/{$product->id}/save")->assertOk();

        // Going down to zero is the opposite of a restock.
        $product->update(['stock' => 0]);

        $this->assertCount(0, $watcher->fresh()->notifications);
    }

    public function test_a_paused_listing_coming_back_into_stock_does_not_notify(): void
    {
        $watcher = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0, 'is_active' => false]);

        $this->actingAs($watcher, 'sanctum')->postJson("/api/products/{$product->id}/save")->assertOk();

        $product->update(['stock' => 4]);

        // Nobody can buy it while it is paused, so telling them is just noise.
        $this->assertCount(0, $watcher->fresh()->notifications);
    }
}
