<?php

namespace Tests\Feature;

use App\Models\GardenBed;
use App\Models\GardenBedPlant;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GardenReminderTest extends TestCase
{
    use RefreshDatabase;

    private function runReminders(string $date): void
    {
        $this->artisan('garden:reminders', ['--now' => $date])->assertSuccessful();
    }

    public function test_a_saved_plant_prompts_a_sowing_reminder_in_its_window(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->create(['name' => 'Broad beans', 'planting_months' => [3, 4]]);
        $user->savedPlants()->attach($plant);

        $this->runReminders('2026-04-10');

        $notification = $user->fresh()->notifications->first();
        $this->assertNotNull($notification);
        $this->assertSame('garden_reminder', $notification->data['type']);
        $this->assertSame('sow', $notification->data['kind']);
        $this->assertStringContainsString('Broad beans', $notification->data['title']);
    }

    public function test_no_sowing_reminder_outside_the_window(): void
    {
        $user = User::factory()->create();
        $user->savedPlants()->attach(Plant::factory()->create(['planting_months' => [3, 4]]));

        $this->runReminders('2026-09-10');

        $this->assertCount(0, $user->fresh()->notifications);
    }

    public function test_a_plant_you_have_not_saved_is_left_alone(): void
    {
        $user = User::factory()->create();
        Plant::factory()->create(['planting_months' => [4]]);

        $this->runReminders('2026-04-10');

        $this->assertCount(0, $user->fresh()->notifications);
    }

    public function test_running_twice_in_the_same_month_only_tells_you_once(): void
    {
        $user = User::factory()->create();
        $user->savedPlants()->attach(Plant::factory()->create(['planting_months' => [4]]));

        $this->runReminders('2026-04-10');
        $this->runReminders('2026-04-11');

        // The scheduler runs every morning, so this is the property that
        // stops a month of identical notifications.
        $this->assertCount(1, $user->fresh()->notifications);
    }

    public function test_the_same_window_next_year_does_remind_again(): void
    {
        $user = User::factory()->create();
        $user->savedPlants()->attach(Plant::factory()->create(['planting_months' => [4]]));

        $this->runReminders('2026-04-10');
        $this->runReminders('2027-04-10');

        $this->assertCount(2, $user->fresh()->notifications);
    }

    public function test_a_crop_past_its_maturity_date_prompts_a_harvest_reminder(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create();
        $plant = Plant::factory()->create(['name' => 'Radish', 'days_to_maturity' => 30, 'planting_months' => [1]]);

        GardenBedPlant::create([
            'garden_bed_id' => $bed->id,
            'plant_id' => $plant->id,
            'planted_at' => '2026-04-01',
        ]);

        $this->runReminders('2026-05-05');

        $notification = $user->fresh()->notifications->first();
        $this->assertSame('harvest', $notification->data['kind']);
        $this->assertStringContainsString('Radish', $notification->data['title']);
    }

    public function test_a_crop_that_is_not_ready_yet_is_left_alone(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create();
        $plant = Plant::factory()->create(['days_to_maturity' => 90, 'planting_months' => [1]]);

        GardenBedPlant::create([
            'garden_bed_id' => $bed->id,
            'plant_id' => $plant->id,
            'planted_at' => '2026-04-01',
        ]);

        $this->runReminders('2026-05-05');

        $this->assertCount(0, $user->fresh()->notifications);
    }

    public function test_a_harvest_reminder_is_only_ever_sent_once(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create();
        $plant = Plant::factory()->create(['days_to_maturity' => 30, 'planting_months' => [1]]);

        GardenBedPlant::create([
            'garden_bed_id' => $bed->id,
            'plant_id' => $plant->id,
            'planted_at' => '2026-04-01',
        ]);

        $this->runReminders('2026-05-05');
        $this->runReminders('2026-06-05');

        // A crop comes ready once, not every day after.
        $this->assertCount(1, $user->fresh()->notifications);
    }

    public function test_an_unplanted_entry_or_a_plant_without_a_maturity_is_skipped(): void
    {
        $user = User::factory()->create();
        $bed = GardenBed::factory()->for($user)->create();

        GardenBedPlant::create([
            'garden_bed_id' => $bed->id,
            'plant_id' => Plant::factory()->create(['days_to_maturity' => 30, 'planting_months' => [1]])->id,
            'planted_at' => null,
        ]);
        GardenBedPlant::create([
            'garden_bed_id' => $bed->id,
            'plant_id' => Plant::factory()->create(['days_to_maturity' => null, 'planting_months' => [1]])->id,
            'planted_at' => '2026-01-01',
        ]);

        $this->runReminders('2026-06-05');

        $this->assertCount(0, $user->fresh()->notifications);
    }
}
