<?php

namespace Tests\Feature;

use App\Models\GardenBed;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\PlantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlotPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlantSeeder::class);
    }

    private function bed(User $user, int $width = 300, int $length = 300): GardenBed
    {
        return GardenBed::factory()->for($user)->create([
            'width_cm' => $width,
            'length_cm' => $length,
        ]);
    }

    private function plant(string $name): Plant
    {
        return Plant::where('name', $name)->firstOrFail();
    }

    public function test_a_bed_can_be_given_a_size(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create(['width_cm' => null, 'length_cm' => null]);

        $response = $this->actingAs($user, 'sanctum')->putJson("/api/garden-beds/{$bed->id}", [
            'name' => $bed->name,
            'width_cm' => 240,
            'length_cm' => 120,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.width_cm', 240)
            ->assertJsonPath('data.has_plot', true);
    }

    public function test_a_bed_smaller_than_thirty_centimetres_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/garden-beds', ['name' => 'Windowsill', 'width_cm' => 10, 'length_cm' => 200])
            ->assertStatus(422)
            ->assertJsonValidationErrors('width_cm');
    }

    public function test_a_plant_can_be_placed_on_the_plot_and_then_moved(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user);

        $entry = $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed->id}/plants", [
                'plant_id' => $this->plant('Tomato')->id,
                'x_cm' => 50,
                'y_cm' => 60,
            ])
            ->assertCreated()
            ->json('entry');

        $this->assertSame(50, $entry['x_cm']);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/garden-beds/{$bed->id}/plants/{$entry['entry_id']}", ['x_cm' => 200, 'y_cm' => 210])
            ->assertOk()
            ->assertJsonPath('entry.x_cm', 200);

        $this->assertDatabaseHas('garden_bed_plants', [
            'id' => $entry['entry_id'],
            'x_cm' => 200,
            'y_cm' => 210,
        ]);
    }

    public function test_a_plant_may_be_added_without_a_position_at_all(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed->id}/plants", ['plant_id' => $this->plant('Basil')->id]);

        $response->assertCreated()->assertJsonPath('entry.x_cm', null);
    }

    public function test_a_position_past_the_edge_of_the_bed_is_rejected(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user, width: 200, length: 100);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed->id}/plants", [
                'plant_id' => $this->plant('Tomato')->id,
                'x_cm' => 190,
                'y_cm' => 150,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('y_cm');
    }

    public function test_half_a_position_is_rejected(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed->id}/plants", [
                'plant_id' => $this->plant('Tomato')->id,
                'x_cm' => 40,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('x_cm');
    }

    public function test_a_plant_cannot_be_placed_on_a_bed_with_no_size(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create(['width_cm' => null, 'length_cm' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/garden-beds/{$bed->id}/plants", [
                'plant_id' => $this->plant('Tomato')->id,
                'x_cm' => 40,
                'y_cm' => 40,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('x_cm');
    }

    public function test_plants_crowding_each_other_are_reported(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user);

        // Tomatoes want 45cm each, so two centres 30cm apart is 15cm short.
        $bed->entries()->create(['plant_id' => $this->plant('Tomato')->id, 'x_cm' => 100, 'y_cm' => 100]);
        $bed->entries()->create(['plant_id' => $this->plant('Tomato')->id, 'x_cm' => 130, 'y_cm' => 100]);

        $issues = $this->actingAs($user, 'sanctum')
            ->getJson("/api/garden-beds/{$bed->id}")
            ->assertOk()
            ->json('issues');

        $this->assertCount(1, $issues);
        $this->assertSame('crowding', $issues[0]['kind']);
        $this->assertStringContainsString('30cm apart', $issues[0]['note']);
    }

    public function test_bad_companions_are_only_flagged_when_they_are_actually_near_each_other(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user, width: 600, length: 300);
        $tomato = $this->plant('Tomato');
        $potato = $this->plant('Potato');

        // Far apart: the same bed on paper, but not sharing any soil.
        $bed->entries()->create(['plant_id' => $tomato->id, 'x_cm' => 50, 'y_cm' => 150]);
        $far = $bed->entries()->create(['plant_id' => $potato->id, 'x_cm' => 550, 'y_cm' => 150]);

        $this->assertSame([], $this->actingAs($user, 'sanctum')
            ->getJson("/api/garden-beds/{$bed->id}")
            ->json('issues'));

        // Dragged to within a metre of the tomatoes, it matters.
        $issues = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/garden-beds/{$bed->id}/plants/{$far->id}", ['x_cm' => 150, 'y_cm' => 150])
            ->assertOk()
            ->json('issues');

        $this->assertContains('companion', array_column($issues, 'kind'));
    }

    public function test_a_plant_too_close_to_the_edge_to_fit_is_reported(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user);

        // A pumpkin wants 90cm, so its centre cannot sit 20cm from the edge.
        $bed->entries()->create(['plant_id' => $this->plant('Pumpkin')->id, 'x_cm' => 20, 'y_cm' => 150]);

        $issues = $this->actingAs($user, 'sanctum')
            ->getJson("/api/garden-beds/{$bed->id}")
            ->json('issues');

        $this->assertSame('overhang', $issues[0]['kind']);
    }

    public function test_a_user_cannot_move_a_plant_in_someone_elses_bed(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $bed = $this->bed($owner);
        $entry = $bed->entries()->create(['plant_id' => $this->plant('Tomato')->id, 'x_cm' => 50, 'y_cm' => 50]);

        $this->actingAs($intruder, 'sanctum')
            ->patchJson("/api/garden-beds/{$bed->id}/plants/{$entry->id}", ['x_cm' => 10, 'y_cm' => 10])
            ->assertForbidden();

        $this->assertDatabaseHas('garden_bed_plants', ['id' => $entry->id, 'x_cm' => 50]);
    }

    public function test_moving_a_plant_leaves_its_notes_alone(): void
    {
        $user = User::factory()->create();
        $bed = $this->bed($user);
        $entry = $bed->entries()->create([
            'plant_id' => $this->plant('Tomato')->id,
            'x_cm' => 50,
            'y_cm' => 50,
            'notes' => 'Sungold, from Ruth next door',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/garden-beds/{$bed->id}/plants/{$entry->id}", ['x_cm' => 90, 'y_cm' => 90])
            ->assertOk();

        $this->assertDatabaseHas('garden_bed_plants', [
            'id' => $entry->id,
            'notes' => 'Sungold, from Ruth next door',
        ]);
    }
}
