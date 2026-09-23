<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A removed course no longer holds its subtopic's open slot (spec 16.2.5), so
 * its student can start afresh. The attempt key still counts it, so the fresh
 * start is the next attempt rather than a second attempt 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->openSlot('AND deleted_at IS NULL');
    }

    public function down(): void
    {
        $this->openSlot('');
    }

    private function openSlot(string $andNotRemoved): void
    {
        DB::statement('DROP INDEX courses_one_open_per_subtopic');
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX courses_one_open_per_subtopic
                ON courses (user_id, module_slug, subtopic_slug)
                WHERE withdrawn_at IS NULL AND completed_at IS NULL {$andNotRemoved}
        SQL);
    }
};
