<?php

namespace Database\Seeders;

use App\Ai\Prompts\PromptSync;
use Illuminate\Database\Seeder;

/**
 * Reads `prompts/` into the registry when the test database is built
 * (spec 15.1.2).
 *
 * The registry is not sample data: without it no prompt has an active
 * version, and every AI call in the suite fails at the gateway. Deploy gets
 * the same rows from `php artisan prompts:sync` (docs/deploy.md); tests get
 * them here, because `migrate:fresh --seeder` is the one hook that runs after
 * the migrations and before the transaction each test is wrapped in.
 *
 * `Tests\TestCase` names this seeder, so every `RefreshDatabase` test has the
 * prompts without asking.
 */
class PromptRegistrySeeder extends Seeder
{
    public function run(PromptSync $sync): void
    {
        $sync->run();
    }
}
