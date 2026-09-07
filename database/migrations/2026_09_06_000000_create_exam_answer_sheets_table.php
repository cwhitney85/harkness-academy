<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sitting of the final exam, in progress (spec 6.3.4, ledger D26).
 *
 * Every other quiz in the system saves itself as it is answered: choosing an
 * option POSTs, is graded, and lands in `quiz_attempts` the instant it is
 * clicked. The final exam cannot do that -- grading it a question at a time
 * would hand out the key mid-exam and a re-sit would be passable from memory
 * (D20) -- so what a student has ticked and not yet handed in lived in React
 * state and died with the tab.
 *
 * **Saving and disclosing are separable, and only disclosure was ever the
 * constraint.** This table holds selections and nothing else: no verdicts, no
 * score, nothing derived from the answer key. `QuizGrader` stays the sole
 * writer of `quiz_attempts` and `correct` stays NOT NULL, because a draft is
 * not an attempt.
 *
 * Rejected (D26): saving ungraded rows into `quiz_attempts`, which needs
 * `correct` to become nullable and weakens an invariant every reader of that
 * table relies on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_answer_sheets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            // Denormalised from the quiz for `quiz_attempts`' reason: a sheet
            // is scoped to a student without a join, and one student's sheet
            // is never another's to read.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The sitting these selections are being written for, 1-based --
            // the number `quiz_attempts.attempt` will carry when the book is
            // handed in. A re-sit is the same exam (D20), so the sitting is
            // what tells two half-finished books apart.
            $table->unsignedSmallInteger('attempt');

            // question id => option id, replaced whole on each save. Ids from
            // the payload rather than foreign keys, as `quiz_attempts` has it.
            $table->jsonb('selections');

            $table->timestamps();

            // One sheet per (exam, student, sitting): a save replaces, never
            // accumulates, and there is no such thing as two drafts of one
            // sitting.
            $table->unique(['quiz_id', 'user_id', 'attempt'], 'exam_answer_sheets_one_per_sitting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answer_sheets');
    }
};
