<?php

namespace Tests\Feature;

use App\Models\Guide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guides_list_only_shows_published_guides(): void
    {
        Guide::factory()->create(['title' => 'Published Guide', 'published_at' => now()->subDay()]);
        Guide::factory()->create(['title' => 'Draft Guide', 'published_at' => null]);

        $response = $this->getJson('/api/guides');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Published Guide'));
        $this->assertFalse($titles->contains('Draft Guide'));
    }

    public function test_guides_can_be_filtered_by_category(): void
    {
        Guide::factory()->create(['category' => 'composting', 'published_at' => now()]);
        Guide::factory()->create(['category' => 'watering', 'published_at' => now()]);

        $response = $this->getJson('/api/guides?category=composting');

        $response->assertOk();
        $categories = collect($response->json('data'))->pluck('category')->unique();

        $this->assertEquals(['composting'], $categories->values()->all());
    }

    public function test_a_single_guide_can_be_fetched_by_slug(): void
    {
        $guide = Guide::factory()->create(['title' => 'Composting 101', 'slug' => 'composting-101']);

        $response = $this->getJson('/api/guides/composting-101');

        $response->assertOk()->assertJsonPath('data.title', 'Composting 101');
    }
}
