<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deleting a user deletes their courses and lessons, and each of those sets a
 * usage row's `course_id` or `lesson_id` null. Postgres re-checks a foreign key
 * on any update to a row written earlier in the same transaction, and it can
 * run that check after the course is gone but before its own set-null has
 * reached the row -- so a user whose usage rows were written in the deleting
 * transaction could not be deleted. Checking the two keys at commit, once
 * every set-null has run, removes the race (spec 14.3.3, lessons T17).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table usage_events alter constraint usage_events_course_id_foreign deferrable initially deferred');
        DB::statement('alter table usage_events alter constraint usage_events_lesson_id_foreign deferrable initially deferred');
    }

    public function down(): void
    {
        DB::statement('alter table usage_events alter constraint usage_events_course_id_foreign not deferrable');
        DB::statement('alter table usage_events alter constraint usage_events_lesson_id_foreign not deferrable');
    }
};
