<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One student's run at one subtopic at one skill level (spec 4.1, ledger D7/D7b).
 *
 * The course carries its own `module_slug` / `subtopic_slug` rather than
 * pointing at the catalog, because those two segments *are* the memory path
 * its documents live under. Freezing them at creation means the catalog can
 * be re-titled later without stranding anybody's knowledge.
 *
 * `status` is where the student is in the pipeline; `withdrawn_at` is whether
 * they are working on it at all (D7b). Keeping them apart is what lets a
 * resumed course land back on the status it held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Path segments, sized so the deepest document path (4.5.3's
            // {module}/{subtopic}/{level}/{lesson-slug}/GUIDANCE.md) stays
            // inside the memories table's 255-character path column.
            $table->string('module_slug', 32);
            $table->string('subtopic_slug', 64);

            // What the student called it -- free text keeps its capitalisation
            // and punctuation here while the slug above stays path-safe.
            $table->string('subtopic_title');

            // 101 / 201 / 301 -- pedagogy, never price (spec 4.5.6).
            $table->unsignedSmallInteger('skill_level');

            // Nth run at this subtopic and level. Retaking a completed course
            // or restarting a withdrawn one increments it (D7b).
            $table->unsignedSmallInteger('attempt')->default(1);

            $table->string('status', 32);

            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            // Named explicitly: the generated name would run past Postgres'
            // 63-character limit and be silently truncated, and CreateCourse
            // recognises this constraint by name when a race trips it.
            $table->unique(
                ['user_id', 'module_slug', 'subtopic_slug', 'skill_level', 'attempt'],
                'courses_one_attempt_per_level',
            );
        });

        // At most one *open* course per student per subtopic (D7a, reworded by
        // D7b). This lives in the database, not only in the transition service,
        // so two requests arriving in the same millisecond cannot both pass an
        // application-side "is anything open?" check and both insert.
        //
        // The terminal-status literal is frozen here on purpose -- a partial
        // index predicate may only reference this row's own columns, so it
        // cannot consult CourseStatus. CourseStatusIndexTest asserts the two
        // never drift apart.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX courses_one_open_per_subtopic
                ON courses (user_id, module_slug, subtopic_slug)
                WHERE withdrawn_at IS NULL AND status <> 'completed'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
