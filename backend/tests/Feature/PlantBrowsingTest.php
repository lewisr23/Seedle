<?php

namespace Tests\Feature;

use App\Models\Plant;
use Database\Seeders\PlantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantBrowsingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlantSeeder::class);
    }

    public function test_plants_can_be_filtered_by_type(): void
    {
        $response = $this->getJson('/api/plants?type=herb');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type')->unique();

        $this->assertEquals(['herb'], $types->values()->all());
    }

    public function test_plants_can_be_filtered_by_sun_requirement(): void
    {
        $response = $this->getJson('/api/plants?sun_requirement=shade');

        $response->assertOk();
        $sun = collect($response->json('data'))->pluck('sun_requirement')->unique();

        $this->assertTrue($sun->isEmpty() || $sun->values()->all() === ['shade']);
    }

    public function test_plants_can_be_filtered_by_zone(): void
    {
        // Rosemary is zone 7-10, so a zone 2 query must exclude it.
        $response = $this->getJson('/api/plants?zone=2');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertFalse($names->contains('Rosemary'));
    }

    public function test_plants_can_be_filtered_by_planting_month(): void
    {
        $garlic = Plant::where('name', 'Garlic')->firstOrFail();
        $month = $garlic->planting_months[0];

        $response = $this->getJson("/api/plants?month={$month}");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Garlic'));
    }

    public function test_a_plant_detail_includes_companion_relationships(): void
    {
        $tomato = Plant::where('name', 'Tomato')->firstOrFail();

        $response = $this->getJson("/api/plants/{$tomato->id}");

        $response->assertOk();

        $good = collect($response->json('data.good_companions'))->pluck('name');
        $bad = collect($response->json('data.bad_companions'))->pluck('name');

        $this->assertTrue($good->contains('Basil'));
        $this->assertTrue($bad->contains('Potato'));
    }
}
