<?php

namespace Tests\Feature;

use App\Models\Guide;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Fabricated users and activity must never reach a live site. These tests fail
 * if someone removes the guards.
 */
class SeedingSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/never run in production/');

        // Called directly rather than through db:seed, which would stop to ask
        // for confirmation before the guard is ever reached.
        $this->app->make(DemoDataSeeder::class)->run();
    }

    public function test_production_seeding_creates_reference_data_but_no_people(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->app->make(DatabaseSeeder::class)->run();

        // Real horticultural content is meant to ship.
        $this->assertGreaterThan(0, Plant::count());
        $this->assertGreaterThan(0, Guide::count());

        // Invented accounts and activity are not.
        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_local_seeding_still_produces_a_usable_dataset(): void
    {
        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertGreaterThan(0, Plant::count());
        $this->assertGreaterThan(1, User::count());
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
