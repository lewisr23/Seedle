<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestedGardenersTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_exclude_yourself_and_people_you_already_follow(): void
    {
        $me = User::factory()->create();
        $alreadyFollowed = User::factory()->create();
        $stranger = User::factory()->create();

        $me->following()->attach($alreadyFollowed->id);

        $response = $this->actingAs($me, 'sanctum')->getJson('/api/suggested-gardeners');

        $response->assertOk();
        $usernames = collect($response->json('data'))->pluck('username');

        $this->assertTrue($usernames->contains($stranger->username));
        $this->assertFalse($usernames->contains($alreadyFollowed->username));
        $this->assertFalse($usernames->contains($me->username));
    }

    public function test_suggestions_are_ordered_by_follower_count(): void
    {
        $me = User::factory()->create();
        $popular = User::factory()->create();
        $quiet = User::factory()->create();

        User::factory()->count(3)->create()->each(fn ($u) => $u->following()->attach($popular->id));

        $response = $this->actingAs($me, 'sanctum')->getJson('/api/suggested-gardeners');

        $response->assertOk();
        $usernames = collect($response->json('data'))->pluck('username');

        $this->assertEquals($popular->username, $usernames->first());
        $this->assertTrue($usernames->contains($quiet->username));
    }

    public function test_suggestions_require_authentication(): void
    {
        $this->getJson('/api/suggested-gardeners')->assertStatus(401);
    }
}
