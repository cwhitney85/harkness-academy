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
        // The preset catalog is not sample data -- without it the course
        // picker has nothing to offer (spec 4.1).
        $this->call(CourseCatalogSeeder::class);

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // The six states package 8 designs against (spec 8.2). Not sample data
        // either: until the engines have been run for real there is nothing in
        // the database for a screen to render.
        $this->call(InterfaceFixtureSeeder::class);
    }
}
