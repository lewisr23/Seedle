<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The plant library and the written guides are real reference content
        // and are meant to ship. Everything else here is local-only.
        $this->call([
            PlantSeeder::class,
            GuideSeeder::class,
        ]);

        if (app()->isProduction()) {
            $this->command?->info('Production: seeded reference data only, no demo accounts or activity.');

            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'hardiness_zone' => '8',
        ]);

        $this->call([DemoDataSeeder::class]);
    }
}
