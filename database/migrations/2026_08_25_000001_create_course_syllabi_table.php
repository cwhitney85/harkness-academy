<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every syllabus a course was ever shown (spec 4.5.4.1, ledger D10.1).
 *
 * A table rather than a column on `courses`, because the rejected drafts and
 * the feedback that killed them are the material prompt tuning runs on. A
 * column keeps the current draft and forgets the argument that produced it.
 *
 * Each row pins the WORKING_KNOWLEDGE and COURSE_CRITERIA versions the model
 * actually read. WK accumulates across every course in a subtopic (ledger D8),
 * so a row storing only feedback and output would replay months later against
 * a document the model never saw -- training on a pair that never happened.
 *
 * `generation` is 1-based and names what it counts: generation 1 is the first
 * draft, which is not a revision. Generations 2..N are the revisions spec
 * 4.5.4 caps. A failed generation writes no row at all, so the cap counts what
 * the student actually saw rather than what was attempted (ledger D10.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_syllabi', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('generation');

            $table->jsonb('syllabus_json');

            // What the student said about the previous generation. Null on
            // generation 1: nothing preceded it to react to.
            $table->text('feedback')->nullable();

            // The exact documents this generation was built from. Not nullable
            // -- the state machine cannot reach syllabus generation without
            // both documents written, so a missing pin is a bug, not a case.
            //
            // Deliberately no Eloquent relation on the model: spec 3.2 makes
            // the path API the only door to memory, and MemoryDoorTest fails
            // any class under app/ that names MemoryVersion.
            $table->foreignId('wk_version_id')->constrained('memory_versions')->cascadeOnDelete();
            $table->foreignId('criteria_version_id')->constrained('memory_versions')->cascadeOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // A double-tapped Revise cannot produce two generation-N rows.
            $table->unique(['course_id', 'generation'], 'course_syllabi_one_row_per_generation');
        });

        // One approved syllabus per course. Approval materialises lesson
        // guidance and creates lesson rows (4.5.5), so a second approved row
        // would mean two syllabi claiming the same lessons.
        //
        // The revision *cap* is deliberately not enforced here: it is policy,
        // it is a natural future plan attribute, and freezing policy into
        // schema is what ledger D7c exists to undo.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX course_syllabi_one_approved_per_course
                ON course_syllabi (course_id)
                WHERE approved_at IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('course_syllabi');
    }
};
