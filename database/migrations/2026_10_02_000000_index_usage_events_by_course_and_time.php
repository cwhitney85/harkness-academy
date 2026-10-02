<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two ways the admin screens read `usage_events` that nothing indexed
 * (spec 17.2.1, 17.3).
 *
 * Postgres does not index a foreign key on its own, so Admin → Courses' cost
 * column scanned every usage row once per course on the page: 8.6 s a page
 * over a million rows, 5 ms with `course_id` indexed. `created_at` serves the
 * seven- and thirty-day sums on Prompts, Models and Usage, four to seven
 * times faster on the same rows, which span 180 days. How much it helps turns
 * on how small a share of the table the range is ([T25](docs/lessons.md)).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_events', function (Blueprint $table): void {
            $table->index('course_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table): void {
            $table->dropIndex(['course_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
