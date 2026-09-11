<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These tests exercise the database-fallback search path, since the CI
 * environment doesn't run a live Elasticsearch cluster. The Elasticsearch
 * query-building itself is covered separately by keeping ProductSearchService
 * thin and delegating the actual HTTP calls to the official client.
 */
class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_returns_only_active_products(): void
    {
        Product::factory()->create(['title' => 'Active Trowel', 'is_active' => true]);
        Product::factory()->create(['title' => 'Inactive Fork', 'is_active' => false]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Active Trowel'));
        $this->assertFalse($titles->contains('Inactive Fork'));
    }

    public function test_product_search_filters_by_category(): void
    {
        Product::factory()->create(['category' => 'tool', 'is_active' => true]);
        Product::factory()->create(['category' => 'seed', 'is_active' => true]);

        $response = $this->getJson('/api/products?category=tool');

        $response->assertOk();
        $categories = collect($response->json('data'))->pluck('category')->unique();

        $this->assertEquals(['tool'], $categories->values()->all());
    }

    public function test_product_search_filters_by_price_range(): void
    {
        Product::factory()->create(['price_pence' => 100, 'is_active' => true]);
        Product::factory()->create(['price_pence' => 5000, 'is_active' => true]);

        $response = $this->getJson('/api/products?min_price=500&max_price=6000');

        $response->assertOk();
        $prices = collect($response->json('data'))->pluck('price_pence');

        $this->assertTrue($prices->every(fn ($p) => $p >= 500 && $p <= 6000));
    }

    public function test_product_search_matches_title_text(): void
    {
        Product::factory()->create(['title' => 'Stainless Steel Trowel', 'is_active' => true]);
        Product::factory()->create(['title' => 'Tomato Feed', 'is_active' => true]);

        $response = $this->getJson('/api/products?q=Trowel');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Stainless Steel Trowel'));
        $this->assertFalse($titles->contains('Tomato Feed'));
    }

    public function test_product_search_can_filter_by_plant(): void
    {
        $tomato = Plant::factory()->create(['name' => 'Test Tomato']);
        $basil = Plant::factory()->create(['name' => 'Test Basil']);

        Product::factory()->create(['title' => 'Tomato Seeds', 'plant_id' => $tomato->id, 'is_active' => true]);
        Product::factory()->create(['title' => 'Basil Seeds', 'plant_id' => $basil->id, 'is_active' => true]);

        $response = $this->getJson("/api/products?plant_id={$tomato->id}");

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Tomato Seeds'));
        $this->assertFalse($titles->contains('Basil Seeds'));
    }

    public function test_product_search_can_filter_to_in_stock_only(): void
    {
        Product::factory()->create(['title' => 'In Stock Tool', 'stock' => 5, 'is_active' => true]);
        Product::factory()->create(['title' => 'Out Of Stock Tool', 'stock' => 0, 'is_active' => true]);

        $response = $this->getJson('/api/products?in_stock=1');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('In Stock Tool'));
        $this->assertFalse($titles->contains('Out Of Stock Tool'));
    }

    public function test_product_search_can_sort_by_price_ascending(): void
    {
        Product::factory()->create(['title' => 'Pricier', 'price_pence' => 5000, 'is_active' => true]);
        Product::factory()->create(['title' => 'Cheaper', 'price_pence' => 500, 'is_active' => true]);

        $response = $this->getJson('/api/products?sort=price_asc');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertEquals('Cheaper', $titles->first());
    }

    public function test_product_search_can_sort_by_price_descending(): void
    {
        Product::factory()->create(['title' => 'Pricier', 'price_pence' => 5000, 'is_active' => true]);
        Product::factory()->create(['title' => 'Cheaper', 'price_pence' => 500, 'is_active' => true]);

        $response = $this->getJson('/api/products?sort=price_desc');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertEquals('Pricier', $titles->first());
    }
}
