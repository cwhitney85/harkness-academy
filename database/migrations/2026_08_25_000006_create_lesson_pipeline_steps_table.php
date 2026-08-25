<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the lesson completion chain has already done (spec 5.4.1).
 *
 * 5.4.1 requires the chain to be "idempotent per (lesson, step)", and this
 * unique index is that requirement rather than a comment about it. A step
 * records itself in the same transaction as its effect, so a re-dispatched
 * chain -- a retry, a parked chain resumed by `lessons:retry-pipeline`, a
 * double-tapped Complete -- finds the row and skips the work instead of
 * paying for a second summary or writing a second WK version.
 *
 * A row means done. A step that failed leaves no row, which is what lets a
 * retry pick up at exactly the step that broke rather than at the top.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_pipeline_steps', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            // A value of App\Lessons\Pipeline\PipelineStep.
            $table->string('step', 32);

            $table->timestamp('completed_at');

            $table->unique(['lesson_id', 'step'], 'lesson_pipeline_steps_once_per_step');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_pipeline_steps');
    }
};
