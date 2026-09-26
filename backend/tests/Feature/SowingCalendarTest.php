<?php

namespace Tests\Feature;

use App\Models\GardenBed;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\PlantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SowingCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlantSeeder::class);
    }

    private function row(array $rows, string $name): array
    {
        foreach ($rows as $row) {
            if ($row['plant']['name'] === $name) {
                return $row;
            }
        }

        $this->fail("No calendar row for {$name}.");
    }

    public function test_the_calendar_is_readable_without_an_account(): void
    {
        $rows = $this->getJson('/api/sowing-calendar')->assertOk()->json('rows');

        $this->assertNotEmpty($rows);
        $this->assertSame([5, 6], $this->row($rows, 'Tomato')['sow_months']);
        $this->assertFalse($this->row($rows, 'Tomato')['in_beds']);
    }

    public function test_harvest_months_are_worked_out_from_days_to_maturity(): void
    {
        $rows = $this->getJson('/api/sowing-calendar')->assertOk()->json('rows');

        // Tomato: sown in May and June, 75 days to maturity, so picking
        // starts around August and runs into September.
        $this->assertSame([8, 9], $this->row($rows, 'Tomato')['harvest_months']);
    }

    public function test_a_crop_that_overwinters_wraps_round_into_the_next_year(): void
    {
        $rows = $this->getJson('/api/sowing-calendar')->assertOk()->json('rows');

        // Garlic goes in during October and November and takes 240 days,
        // which lands in June and July rather than month 18 and 19.
        $garlic = $this->row($rows, 'Garlic');

        $this->assertSame([10, 11], $garlic['sow_months']);
        $this->assertSame([6, 7], $garlic['harvest_months']);
    }

    public function test_a_zone_filters_out_plants_that_will_not_survive_it(): void
    {
        // Rosemary is zone 7 and up; a zone 4 garden should not be offered it.
        $names = collect($this->getJson('/api/sowing-calendar?zone=4')->assertOk()->json('rows'))
            ->pluck('plant.name');

        $this->assertTrue($names->contains('Cabbage'));
        $this->assertFalse($names->contains('Rosemary'));
    }

    public function test_what_you_are_growing_is_marked_and_sorted_to_the_top(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create(['name' => 'Top bed']);
        $courgette = Plant::where('name', 'Courgette')->firstOrFail();
        $bed->entries()->create(['plant_id' => $courgette->id, 'planted_at' => '2026-05-20']);

        $rows = $this->actingAs($user, 'sanctum')
            ->getJson('/api/sowing-calendar')
            ->assertOk()
            ->json('rows');

        $this->assertSame('Courgette', $rows[0]['plant']['name']);
        $this->assertTrue($rows[0]['in_beds']);
        $this->assertSame('Top bed', $rows[0]['plantings'][0]['bed_name']);
        // 50 days from 20 May.
        $this->assertSame('2026-07-09', $rows[0]['plantings'][0]['ready_on']);
    }

    public function test_saved_plants_are_marked_and_rank_above_the_rest_of_the_library(): void
    {
        $user = User::factory()->create();
        $user->savedPlants()->attach(Plant::where('name', 'Radish')->firstOrFail()->id);

        $rows = $this->actingAs($user, 'sanctum')
            ->getJson('/api/sowing-calendar')
            ->assertOk()
            ->json('rows');

        $this->assertSame('Radish', $rows[0]['plant']['name']);
        $this->assertTrue($rows[0]['saved']);
        $this->assertFalse($rows[0]['in_beds']);
    }

    public function test_the_zone_defaults_to_the_one_on_your_profile(): void
    {
        $user = User::factory()->create(['hardiness_zone' => '4']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/sowing-calendar')->assertOk();

        $response->assertJsonPath('zone', 4);
        $this->assertFalse(
            collect($response->json('rows'))->pluck('plant.name')->contains('Rosemary')
        );
    }
}
