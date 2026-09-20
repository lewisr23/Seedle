<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Product;
use App\Models\User;
use App\Models\Want;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WantTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_gardener_can_post_a_request(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/wants', [
                'title' => 'Looking for rhubarb crowns',
                'description' => 'Happy to collect.',
                'plant_id' => $plant->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Looking for rhubarb crowns')
            ->assertJsonPath('data.is_open', true);

        $this->assertDatabaseHas('wants', ['user_id' => $user->id, 'plant_id' => $plant->id]);
    }

    public function test_posting_a_request_needs_an_account(): void
    {
        $this->postJson('/api/wants', ['title' => 'Anything'])->assertUnauthorized();
    }

    public function test_a_title_is_required_and_bounded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/wants', ['title' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('title');

        $this->actingAs($user, 'sanctum')->postJson('/api/wants', ['title' => str_repeat('a', 121)])
            ->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_browsing_shows_open_requests_newest_first_and_hides_closed_ones(): void
    {
        $older = Want::factory()->create();
        $this->travel(5)->minutes();
        $newer = Want::factory()->create();
        $closed = Want::factory()->create(['is_open' => false]);

        $ids = collect($this->getJson('/api/wants')->assertOk()->json('data'))->pluck('id');

        $this->assertSame([$newer->id, $older->id], $ids->all());
        $this->assertFalse($ids->contains($closed->id));
    }

    public function test_closed_requests_can_be_asked_for_explicitly(): void
    {
        $closed = Want::factory()->create(['is_open' => false]);

        $ids = collect($this->getJson('/api/wants?include_closed=1')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($closed->id));
    }

    public function test_the_owner_can_close_their_request(): void
    {
        $want = Want::factory()->create();

        $this->actingAs($want->user, 'sanctum')
            ->patchJson("/api/wants/{$want->id}/close")
            ->assertOk()
            ->assertJsonPath('data.is_open', false);
    }

    public function test_somebody_else_cannot_close_or_delete_your_request(): void
    {
        $want = Want::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')->patchJson("/api/wants/{$want->id}/close")->assertForbidden();
        $this->actingAs($intruder, 'sanctum')->deleteJson("/api/wants/{$want->id}")->assertForbidden();

        $this->assertDatabaseHas('wants', ['id' => $want->id, 'is_open' => true]);
    }

    public function test_the_owner_can_delete_their_request(): void
    {
        $want = Want::factory()->create();

        $this->actingAs($want->user, 'sanctum')->deleteJson("/api/wants/{$want->id}")->assertOk();

        $this->assertDatabaseMissing('wants', ['id' => $want->id]);
    }

    public function test_a_new_listing_tells_everyone_waiting_for_that_plant(): void
    {
        $plant = Plant::factory()->create();
        $asker = User::factory()->create();
        $uninterested = User::factory()->create();

        Want::factory()->create(['user_id' => $asker->id, 'plant_id' => $plant->id]);
        Want::factory()->create(['user_id' => $uninterested->id, 'plant_id' => Plant::factory()->create()->id]);

        Product::factory()->create(['plant_id' => $plant->id, 'is_active' => true, 'stock' => 3]);

        $this->assertCount(1, $asker->fresh()->notifications);
        $this->assertSame('want_matched', $asker->fresh()->notifications->first()->data['type']);
        $this->assertCount(0, $uninterested->fresh()->notifications);
    }

    public function test_your_own_listing_does_not_notify_you(): void
    {
        $plant = Plant::factory()->create();
        $grower = User::factory()->create();
        Want::factory()->create(['user_id' => $grower->id, 'plant_id' => $plant->id]);

        Product::factory()->for($grower, 'seller')->create([
            'plant_id' => $plant->id, 'is_active' => true, 'stock' => 3,
        ]);

        $this->assertCount(0, $grower->fresh()->notifications);
    }

    public function test_a_closed_request_is_not_notified(): void
    {
        $plant = Plant::factory()->create();
        $asker = User::factory()->create();
        Want::factory()->create(['user_id' => $asker->id, 'plant_id' => $plant->id, 'is_open' => false]);

        Product::factory()->create(['plant_id' => $plant->id, 'is_active' => true, 'stock' => 3]);

        $this->assertCount(0, $asker->fresh()->notifications);
    }

    public function test_a_paused_or_empty_listing_does_not_notify(): void
    {
        $plant = Plant::factory()->create();
        $asker = User::factory()->create();
        Want::factory()->create(['user_id' => $asker->id, 'plant_id' => $plant->id]);

        Product::factory()->create(['plant_id' => $plant->id, 'is_active' => false, 'stock' => 5]);
        Product::factory()->create(['plant_id' => $plant->id, 'is_active' => true, 'stock' => 0]);

        // Nothing claimable, so nothing worth interrupting anyone for.
        $this->assertCount(0, $asker->fresh()->notifications);
    }
}
