<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_only_shows_posts_from_followed_users_and_self(): void
    {
        $me = User::factory()->create();
        $followed = User::factory()->create();
        $stranger = User::factory()->create();

        $myPost = Post::factory()->for($me)->create(['body' => 'My own update']);
        $followedPost = Post::factory()->for($followed)->create(['body' => 'Followed update']);
        $strangerPost = Post::factory()->for($stranger)->create(['body' => 'Stranger update']);

        $me->following()->attach($followed->id);

        $response = $this->actingAs($me, 'sanctum')->getJson('/api/feed');

        $response->assertOk();
        $bodies = collect($response->json('data'))->pluck('body');

        $this->assertTrue($bodies->contains('My own update'));
        $this->assertTrue($bodies->contains('Followed update'));
        $this->assertFalse($bodies->contains('Stranger update'));
    }

    public function test_a_user_can_like_and_unlike_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/posts/{$post->id}/like")->assertOk();
        $this->assertDatabaseHas('likes', ['post_id' => $post->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/posts/{$post->id}/like")->assertOk();
        $this->assertDatabaseMissing('likes', ['post_id' => $post->id, 'user_id' => $user->id]);
    }

    public function test_liking_the_same_post_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/posts/{$post->id}/like");
        $this->actingAs($user, 'sanctum')->postJson("/api/posts/{$post->id}/like");

        $this->assertDatabaseCount('likes', 1);
    }

    public function test_a_user_cannot_delete_someone_elses_post(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        $response = $this->actingAs($intruder, 'sanctum')->deleteJson("/api/posts/{$post->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_a_user_can_comment_on_a_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Great tip, trying this today.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('comments', ['post_id' => $post->id, 'user_id' => $user->id]);
    }
}
