<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What was said during a course's two interviews (spec 4.3, 4.4).
 *
 * Rows, not a JSON blob on the course, because an interview is appended to a
 * turn at a time while a stream is in flight (ledger D7).
 *
 * **Deliberately short-lived.** The interview exists to produce one document;
 * once that document is written the rows are deleted, which is spec 4.3's
 * "transcript discarded after summarization" made into a fact rather than a
 * promise. A row surviving its course's WORKING_KNOWLEDGE.md is a bug.
 *
 * Separate from the lesson transcripts of spec 5.2.6 on purpose: those are
 * kept (5.3 resumes from them) and belong to a lesson, not a course. One
 * table with two retention rules would make "is this row garbage?" a question
 * you have to look at a discriminator column to answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_messages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // Which of the course's two interviews. The knowledge transcript
            // is gone before the goals interview opens, so these never
            // coexist -- the column is what lets a test say so.
            $table->string('kind', 16);

            $table->string('speaker', 16);

            $table->text('content');

            // Order of speaking. Unique within the interview so a
            // double-submitted turn collides instead of duplicating.
            $table->unsignedSmallInteger('position');

            $table->timestamp('created_at');

            $table->unique(['course_id', 'kind', 'position'], 'interview_messages_one_turn_per_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_messages');
    }
};
