<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every quiz the system generates, and every answer to one (spec 6.1).
 *
 * **Package 5 lands this schema, package 6.1 lands the service over it**
 * (ledger D12). Spec 5.2.2 and 5.2.4 name these two tables in package 5's own
 * done-conditions -- a checkpoint is a quiz and grading it writes an attempt
 * -- so the lesson engine cannot reach a done-condition without them. What
 * 6.1 still owns is the single service that creates all four kinds and the
 * three kinds package 5 does not write.
 *
 * Polymorphic from the start rather than checkpoint-shaped now and widened
 * later: the four kinds differ in what they hang off, not in what they hold.
 * A checkpoint and a final exam are the same `json/quiz.v1` document with
 * different scope, which is exactly what 2.3.2 means by "one schema, three
 * consumers".
 *
 * `payload` holds the answer key. Nothing reads this column straight to a
 * client -- 5.2.3 strips `correct_option_id` and `explanation` server-side
 * until a question is answered -- and keeping the key here is what makes
 * grading code rather than a second model call (ledger D5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The course whose syllabus declares the concept vocabulary this
            // quiz's tags resolve against (4.5.2.2). Present for every kind,
            // including the grains that do not hang off a course row.
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // checkpoint | homework | final | refresher.
            $table->string('kind', 16);

            // What the quiz is about. A checkpoint and homework hang off a
            // lesson; a final exam off a course. What a subtopic-grain
            // refresher hangs off is 6.4's call -- it is the one grain with no
            // obvious row, and guessing at it here would be package 5
            // designing package 6 (ledger D10.2's warning).
            $table->morphs('quizzable');

            // Set only for a checkpoint: the section it closes, 1-based.
            $table->unsignedSmallInteger('section_index')->nullable();

            // A `json/checkpoint.v1` or `json/quiz.v1` document, answer key
            // included, exactly as the model produced it (5.2.2's lossless
            // round-trip).
            $table->jsonb('payload');

            $table->timestamps();
        });

        // One quiz per scope: one checkpoint per section, one homework per
        // lesson, one final per course. A student who reconnects to a section
        // that already has a checkpoint gets that one back, rather than paying
        // for a second generation and being asked new questions about the same
        // prose (5.2.6).
        //
        // Expression index rather than a plain unique, because `section_index`
        // is null for every kind but a checkpoint, and Postgres treats nulls
        // as distinct from each other -- a plain unique would let a lesson
        // collect any number of homework quizzes. `NULLS NOT DISTINCT` would
        // say this more directly but needs Postgres 15; the sentinel needs
        // nothing, and -1 cannot collide with a section index because sections
        // are numbered from one.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quizzes_one_per_scope
                ON quizzes (quizzable_type, quizzable_id, kind, (COALESCE(section_index, -1)))
        SQL);

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            // Denormalised from the quiz so an attempt can be scoped to a
            // student without a join -- and so a quiz shared across students
            // later does not need this table changed.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ids from the payload, not foreign keys: questions and options
            // live inside the JSON document (ledger D10.2's rule -- nothing
            // queries by them).
            $table->string('question_id', 64);
            $table->string('option_id', 64);

            $table->boolean('correct');

            // 1-based. A checkpoint never gates Continue (5.2.4), so answering
            // the same question again is ordinary behaviour, not an error, and
            // the counter is what keeps both answers instead of overwriting
            // the first with the second.
            $table->unsignedSmallInteger('attempt');

            $table->timestamp('created_at');

            $table->unique(['quiz_id', 'user_id', 'question_id', 'attempt'], 'quiz_attempts_one_row_per_attempt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quizzes');
    }
};
