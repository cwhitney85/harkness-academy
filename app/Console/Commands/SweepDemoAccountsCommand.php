<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Spec 12.5.1 -- unclaimed demo accounts are deleted once they are old enough.
 *
 * The one hard delete in the app (ledger D68), of demo-plan rows only, and of
 * one whose visitor already deleted it too. A delete and nothing else. Every table that hangs off a user cascades --
 * courses and everything under them, the memory tree, quizzes, attempts,
 * answer sheets, refusals -- and `usage_events` sets `user_id` null, so what a
 * demo cost stays countable after the visitor is gone.
 */
class SweepDemoAccountsCommand extends Command
{
    protected $signature = 'demo:sweep';

    protected $description = 'Spec 12.5.1: delete demo-plan accounts older than courses.demo_account_days';

    public function handle(): int
    {
        $deleted = User::withTrashed()
            ->where('plan', 'demo')
            ->where('created_at', '<', now()->subDays(config('courses.demo_account_days')))
            ->forceDelete();

        $this->components->info("Deleted {$deleted} unclaimed demo accounts.");

        return self::SUCCESS;
    }
}
