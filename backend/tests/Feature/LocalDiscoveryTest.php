<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\Location\PostcodeGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    /** Roughly Bristol, Bath and Edinburgh. */
    private const BRISTOL = [51.45, -2.59];

    private const BATH = [51.38, -2.36];

    private const EDINBURGH = [55.95, -3.19];

    private function gardenerAt(array $point): User
    {
        return User::factory()->create(['latitude' => $point[0], 'longitude' => $point[1]]);
    }

    public function test_a_postcode_is_geocoded_and_stored_when_the_profile_is_saved(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response([
            'result' => ['latitude' => 51.4545, 'longitude' => -2.5879],
        ])]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['postcode' => 'bs1 4df'])
            ->assertOk();

        $user->refresh();
        // Normalised on the way in, and rounded to about a kilometre so the
        // stored point is a neighbourhood rather than an address.
        $this->assertSame('BS14DF', $user->postcode);
        $this->assertSame(51.45, $user->latitude);
        $this->assertSame(-2.59, $user->longitude);
    }

    public function test_an_unrecognised_postcode_still_saves_the_profile_without_coordinates(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response([], 404)]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['postcode' => 'ZZ99 9ZZ', 'bio' => 'Allotment 14'])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Allotment 14', $user->bio);
        $this->assertNull($user->latitude);
    }

    public function test_a_lookup_failure_does_not_break_saving(): void
    {
        Http::fake(fn () => throw new \RuntimeException('network down'));

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['postcode' => 'BS1 4DF'])
            ->assertOk();

        $this->assertNull($user->fresh()->latitude);
    }

    public function test_clearing_the_postcode_clears_the_coordinates(): void
    {
        $user = $this->gardenerAt(self::BRISTOL);
        $user->update(['postcode' => 'BS14DF']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/me', ['postcode' => null])
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->postcode);
        $this->assertNull($user->latitude);
    }

    public function test_a_radius_search_returns_only_nearby_listings(): void
    {
        $viewer = $this->gardenerAt(self::BRISTOL);
        $near = Product::factory()->for($this->gardenerAt(self::BATH), 'seller')->create(['is_active' => true]);
        $far = Product::factory()->for($this->gardenerAt(self::EDINBURGH), 'seller')->create(['is_active' => true]);

        $ids = collect(
            $this->actingAs($viewer, 'sanctum')->getJson('/api/products?radius_km=40')->assertOk()->json('data')
        )->pluck('id');

        // Bath is about 20km from Bristol; Edinburgh is several hundred.
        $this->assertTrue($ids->contains($near->id));
        $this->assertFalse($ids->contains($far->id));
    }

    public function test_a_radius_search_reports_the_distance(): void
    {
        $viewer = $this->gardenerAt(self::BRISTOL);
        Product::factory()->for($this->gardenerAt(self::BATH), 'seller')->create(['is_active' => true]);

        $row = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/products?radius_km=40')
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('distance_km', $row);
        $this->assertGreaterThan(5, $row['distance_km']);
        $this->assertLessThan(40, $row['distance_km']);
    }

    public function test_sellers_without_a_location_are_left_out_of_a_radius_search(): void
    {
        $viewer = $this->gardenerAt(self::BRISTOL);
        $placeless = Product::factory()->for(User::factory()->create(), 'seller')->create(['is_active' => true]);

        $ids = collect(
            $this->actingAs($viewer, 'sanctum')->getJson('/api/products?radius_km=500')->assertOk()->json('data')
        )->pluck('id');

        $this->assertFalse($ids->contains($placeless->id));
    }

    public function test_a_viewer_without_a_location_gets_an_unfiltered_search(): void
    {
        $viewer = User::factory()->create(['latitude' => null, 'longitude' => null]);
        $far = Product::factory()->for($this->gardenerAt(self::EDINBURGH), 'seller')->create(['is_active' => true]);

        $ids = collect(
            $this->actingAs($viewer, 'sanctum')->getJson('/api/products?radius_km=5')->assertOk()->json('data')
        )->pluck('id');

        // Nothing to measure from, so the radius is ignored rather than
        // silently returning an empty shelf.
        $this->assertTrue($ids->contains($far->id));
    }

    public function test_sorting_by_distance_puts_the_closest_first(): void
    {
        $viewer = $this->gardenerAt(self::BRISTOL);
        $further = Product::factory()->for($this->gardenerAt(self::BATH), 'seller')->create(['is_active' => true]);
        $closer = Product::factory()->for($this->gardenerAt([51.46, -2.60]), 'seller')->create(['is_active' => true]);

        $ids = collect(
            $this->actingAs($viewer, 'sanctum')
                ->getJson('/api/products?radius_km=100&sort=distance')
                ->assertOk()
                ->json('data')
        )->pluck('id');

        $this->assertSame([$closer->id, $further->id], $ids->take(2)->all());
    }

    public function test_a_postcode_is_only_looked_up_once(): void
    {
        Http::fake(['api.postcodes.io/*' => Http::response([
            'result' => ['latitude' => 51.4545, 'longitude' => -2.5879],
        ])]);

        $geocoder = $this->app->make(PostcodeGeocoder::class);
        $geocoder->lookup('BS1 4DF');
        $geocoder->lookup('bs14df');

        // Same postcode normalised the same way, so the second call is served
        // from cache rather than hitting the service again.
        Http::assertSentCount(1);
    }
}
