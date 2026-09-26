<?php

namespace Tests\Feature;

use App\Models\GardenBed;
use App\Models\GardenBedPlant;
use App\Models\Harvest;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\PlantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlantSeeder::class);
    }

    private function entryFor(User $user, string $plantName = 'Courgette'): GardenBedPlant
    {
        $bed = GardenBed::factory()->for($user)->create(['width_cm' => 300, 'length_cm' => 200]);

        return $bed->entries()->create([
            'plant_id' => Plant::where('name', $plantName)->firstOrFail()->id,
            'planted_at' => '2026-05-01',
        ]);
    }

    public function test_a_gardener_can_log_a_harvest_against_what_they_planted(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/garden-beds/{$entry->garden_bed_id}/plants/{$entry->id}/harvests",
            ['harvested_at' => '2026-07-14', 'quantity' => 1.5, 'unit' => 'kg', 'notes' => 'Four good ones']
        );

        $response->assertCreated()
            ->assertJsonPath('quantity', 1.5)
            ->assertJsonPath('unit', 'kg')
            ->assertJsonPath('plant.name', 'Courgette');

        $this->assertDatabaseHas('harvests', [
            'garden_bed_plant_id' => $entry->id,
            'user_id' => $user->id,
            'unit' => 'kg',
        ]);
    }

    public function test_a_harvest_can_be_logged_without_weighing_anything(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        $this->actingAs($user, 'sanctum')->postJson(
            "/api/garden-beds/{$entry->garden_bed_id}/plants/{$entry->id}/harvests",
            ['harvested_at' => '2026-07-14', 'notes' => 'Picked a few for tea']
        )->assertCreated()->assertJsonPath('quantity', null);
    }

    public function test_a_harvest_cannot_be_logged_in_the_future(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        $this->actingAs($user, 'sanctum')->postJson(
            "/api/garden-beds/{$entry->garden_bed_id}/plants/{$entry->id}/harvests",
            ['harvested_at' => now()->addWeek()->toDateString()]
        )->assertStatus(422)->assertJsonValidationErrors('harvested_at');
    }

    public function test_an_unknown_unit_is_rejected(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        $this->actingAs($user, 'sanctum')->postJson(
            "/api/garden-beds/{$entry->garden_bed_id}/plants/{$entry->id}/harvests",
            ['harvested_at' => '2026-07-14', 'quantity' => 3, 'unit' => 'wheelbarrows']
        )->assertStatus(422)->assertJsonValidationErrors('unit');
    }

    public function test_a_gardener_cannot_log_a_harvest_in_someone_elses_bed(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $entry = $this->entryFor($owner);

        $this->actingAs($intruder, 'sanctum')->postJson(
            "/api/garden-beds/{$entry->garden_bed_id}/plants/{$entry->id}/harvests",
            ['harvested_at' => '2026-07-14']
        )->assertForbidden();

        $this->assertDatabaseCount('harvests', 0);
    }

    public function test_the_log_only_shows_your_own_harvests(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->entryFor($user);
        $theirs = $this->entryFor($other);

        Harvest::create(['garden_bed_plant_id' => $mine->id, 'user_id' => $user->id, 'harvested_at' => '2026-07-01']);
        Harvest::create(['garden_bed_plant_id' => $theirs->id, 'user_id' => $other->id, 'harvested_at' => '2026-07-02']);

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/harvests')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('2026-07-01', $data[0]['harvested_at']);
    }

    public function test_the_summary_keeps_different_units_of_the_same_plant_apart(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user, 'Courgette');

        // Two weighings and one count of the same plant. Adding 2.5kg to
        // 6 fruit would be a made-up number, so they stay as two lines.
        foreach ([['2026-07-01', 1.5, 'kg'], ['2026-07-08', 1.0, 'kg'], ['2026-07-15', 6, 'count']] as [$date, $qty, $unit]) {
            Harvest::create([
                'garden_bed_plant_id' => $entry->id,
                'user_id' => $user->id,
                'harvested_at' => $date,
                'quantity' => $qty,
                'unit' => $unit,
            ]);
        }

        $totals = $this->actingAs($user, 'sanctum')
            ->getJson('/api/harvests/summary?year=2026')
            ->assertOk()
            ->assertJsonPath('year', 2026)
            ->json('totals');

        $byUnit = collect($totals)->keyBy('unit');

        $this->assertCount(2, $totals);
        $this->assertSame(2.5, $byUnit['kg']['quantity']);
        $this->assertSame(2, $byUnit['kg']['times']);
        // Whole numbers come back from JSON as ints, so compare loosely.
        $this->assertEquals(6, $byUnit['count']['quantity']);
    }

    public function test_the_summary_offers_the_years_that_actually_have_something_in_them(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user);

        Harvest::create(['garden_bed_plant_id' => $entry->id, 'user_id' => $user->id, 'harvested_at' => '2025-08-20']);
        Harvest::create(['garden_bed_plant_id' => $entry->id, 'user_id' => $user->id, 'harvested_at' => '2026-07-01']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/harvests/summary?year=2026')
            ->assertOk()
            ->assertJsonPath('years', [2026, 2025]);
    }

    public function test_a_gardener_can_delete_their_own_harvest_but_not_anyone_elses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $entry = $this->entryFor($user);
        $theirEntry = $this->entryFor($other);

        $mine = Harvest::create(['garden_bed_plant_id' => $entry->id, 'user_id' => $user->id, 'harvested_at' => '2026-07-01']);
        $theirs = Harvest::create(['garden_bed_plant_id' => $theirEntry->id, 'user_id' => $other->id, 'harvested_at' => '2026-07-01']);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/harvests/{$theirs->id}")->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/harvests/{$mine->id}")->assertOk();

        $this->assertDatabaseMissing('harvests', ['id' => $mine->id]);
        $this->assertDatabaseHas('harvests', ['id' => $theirs->id]);
    }

    public function test_pulling_a_plant_out_of_a_bed_takes_its_harvests_with_it(): void
    {
        $user = User::factory()->create();
        $entry = $this->entryFor($user);
        Harvest::create(['garden_bed_plant_id' => $entry->id, 'user_id' => $user->id, 'harvested_at' => '2026-07-01']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/garden-beds/{$entry->garden_bed_id}/plants/{$entry->id}")
            ->assertOk();

        $this->assertDatabaseCount('harvests', 0);
    }
}
