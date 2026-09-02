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

        // Idempotent, like the catalog above: seeding twice does not make a
        // second one, and nothing here touches a course.
        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        // InterfaceFixtureSeeder is deliberately NOT called here.
        //
        // It exists to give package 8's screens something to render before the
        // engines had ever been run (spec 8.2), and it gets there by deleting
        // every course belonging to colin@opentutor.test first -- which, now
        // that a real course is being taught to that same student, is their
        // progress. It is run when it is wanted and never as a side effect of
        // `--seed` (Colin, 2026-09-02):
        //
        //     php artisan db:seed --class=InterfaceFixtureSeeder
        //
        // The tests that assert 8.2's six states call it directly, against
        // the `opentutor_test` database, so nothing is lost by its absence
        // here.
    }
}
