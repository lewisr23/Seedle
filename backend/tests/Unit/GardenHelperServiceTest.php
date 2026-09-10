<?php

namespace Tests\Unit;

use App\Models\GardenBed;
use App\Models\Plant;
use App\Services\Garden\GardenHelperService;
use Database\Seeders\PlantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GardenHelperServiceTest extends TestCase
{
    use RefreshDatabase;

    private GardenHelperService $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlantSeeder::class);
        $this->helper = new GardenHelperService;
    }

    public function test_recommended_plants_excludes_plants_outside_the_zone(): void
    {
        // Rosemary is zone 7-10 only.
        $recommendations = $this->helper->recommendedPlants(zone: 2, month: (int) Plant::where('name', 'Rosemary')->first()->planting_months[0]);

        $this->assertFalse($recommendations->pluck('name')->contains('Rosemary'));
    }

    public function test_recommended_plants_excludes_plants_outside_the_planting_month(): void
    {
        $tomato = Plant::where('name', 'Tomato')->firstOrFail();
        [$plantableMonth] = $tomato->planting_months;
        $offMonth = $plantableMonth === 12 ? 1 : $plantableMonth + 1;
        while (in_array($offMonth, $tomato->planting_months, true)) {
            $offMonth = $offMonth === 12 ? 1 : $offMonth + 1;
        }

        $recommendations = $this->helper->recommendedPlants(zone: $tomato->min_zone, month: $offMonth);

        $this->assertFalse($recommendations->pluck('name')->contains('Tomato'));
    }

    public function test_checking_a_new_plant_against_a_bed_flags_bad_companions(): void
    {
        $bed = GardenBed::factory()->create();
        $tomato = Plant::where('name', 'Tomato')->firstOrFail();
        $cabbage = Plant::where('name', 'Cabbage')->firstOrFail();

        $bed->entries()->create(['plant_id' => $tomato->id]);

        $conflicts = $this->helper->checkNewPlantAgainstBed($bed, $cabbage);

        $this->assertCount(1, $conflicts);
        $this->assertEquals('Tomato', $conflicts[0]['plant']->name);
    }

    public function test_checking_a_good_companion_returns_no_conflicts(): void
    {
        $bed = GardenBed::factory()->create();
        $tomato = Plant::where('name', 'Tomato')->firstOrFail();
        $basil = Plant::where('name', 'Basil')->firstOrFail();

        $bed->entries()->create(['plant_id' => $tomato->id]);

        $conflicts = $this->helper->checkNewPlantAgainstBed($bed, $basil);

        $this->assertCount(0, $conflicts);
    }
}
