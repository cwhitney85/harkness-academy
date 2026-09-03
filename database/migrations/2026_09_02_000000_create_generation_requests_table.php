<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `top`-tier generation the student asked for and is waiting on (ledger D25).
 *
 * Syllabus drafting and exam writing take minutes, and a request held open for
 * minutes is cut by the edge long before the model finishes -- found in
 * production on 2026-09-02, where the browser was told 504 while the
 * generation ran on and wrote its row. Both moved onto the queue, and the
 * screens they belong to need something to read while the work is out.
 *
 * A row here means exactly one thing: **work was asked for and has not landed
 * yet**. It is created when the student presses the button, and deleted the
 * moment the thing they asked for exists. That absence-is-the-answer shape is
 * package 5's (`quizzes`, spec 5.4.5): the artefact's own existence is the
 * completion signal, and nothing writes a placeholder that a later reader
 * could mistake for the real thing.
 *
 * The exception is failure. `SyllabusGenerator` and `FinalExam` both fail
 * closed -- nothing written, nothing moved -- which used to be reported in the
 * response to the very request that failed. On the queue there is no such
 * response, so the row survives its own failure carrying the sentence the
 * student is owed, and the next request clears it.
 *
 * One row per (course, kind), which is what makes a double-tapped button cost
 * nothing: the second press finds the first request still open and dispatches
 * no second call. Before this table each press was its own Opus generation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_requests', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // `GenerationKind`. A string rather than an FK for the same reason
            // `courses.status` is one: the set is the app's vocabulary, not
            // catalog data a row could be added to.
            $table->string('kind');

            $table->timestamp('requested_at');

            // Null while the work is still out. Set when the generator gave up
            // -- which it does only after its own retry (4.5.1, 6.3).
            $table->timestamp('failed_at')->nullable();

            // The student's sentence, not the developer's. Whatever renders
            // this is a screen, and the exceptions carry two different
            // audiences' wording (`ResumeRefusal`'s precedent).
            $table->text('failure')->nullable();

            $table->timestamps();

            // The whole double-tap guard, enforced rather than checked: two
            // concurrent presses cannot both open a request.
            $table->unique(['course_id', 'kind'], 'generation_requests_one_per_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_requests');
    }
};
