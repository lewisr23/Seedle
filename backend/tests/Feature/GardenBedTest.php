<?php

namespace Tests\Feature;

use App\Models\GardenBed;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\PlantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GardenBedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlantSeeder::class);
    }

    public function test_a_user_can_create_a_garden_bed_and_add_a_plant(): void
    {
        $user = User::factory()->create();
        $tomato = Plant::where('name', 'Tomato')->firstOrFail();

        $bed = $this->actingAs($user, 'sanctum')
            ->postJson('/api/garden-beds', ['name' => 'Patio Bed'])
            ->assertCreated()
            ->json('data');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed['id']}/plants", ['plant_id' => $tomato->id]);

        $response->assertCreated()->assertJsonPath('warnings', []);

        $this->assertDatabaseHas('garden_bed_plants', [
            'garden_bed_id' => $bed['id'],
            'plant_id' => $tomato->id,
        ]);
    }

    public function test_adding_a_bad_companion_plant_returns_a_warning(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create();
        $tomato = Plant::where('name', 'Tomato')->firstOrFail();
        $potato = Plant::where('name', 'Potato')->firstOrFail();

        $bed->entries()->create(['plant_id' => $tomato->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed->id}/plants", ['plant_id' => $potato->id]);

        $response->assertCreated();
        $warnings = $response->json('warnings');

        $this->assertCount(1, $warnings);
        $this->assertEquals('Tomato', $warnings[0]['plant']['name']);
    }

    public function test_a_user_cannot_view_another_users_garden_bed(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $bed = GardenBed::factory()->for($owner)->create();

        $response = $this->actingAs($intruder, 'sanctum')->getJson("/api/garden-beds/{$bed->id}");

        $response->assertForbidden();
    }
}
