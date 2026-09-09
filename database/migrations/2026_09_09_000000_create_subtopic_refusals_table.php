<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A free-text subtopic the scope check turned away (spec 4.9.7, ledger D42).
 *
 * The only record that a course start failed. Everywhere else in the pipeline
 * a refusal leaves something behind -- a withdrawn course, a draft, a usage
 * row that names what it was for -- but 4.9 refuses before any row is written,
 * so without this the whole event is a flash message and a `usage_events` row
 * that cannot say what the student typed.
 *
 * `reason` is the model's own sentence. It is stored and never rendered: the
 * student reads copy generated from the verdict (4.9.6), because the same
 * probes that produced these verdicts also produced fluent, confident and
 * wrong explanations of them. It is here for an admin UI to read back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtopic_refusals', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // What the student typed, normalised the way `SubtopicName` does
            // -- the words the refusal was actually about.
            $table->string('typed_name');

            // Slugs rather than foreign keys, as `courses` has it: the module
            // a refusal named should still read the same after the catalog is
            // re-titled or the row is gone.
            $table->string('module_slug');
            $table->string('verdict');
            $table->string('suggested_module_slug')->nullable();

            $table->text('reason');

            $table->timestamps();

            // The one question this table is asked: what is this student being
            // turned away from, and what did they type.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtopic_refusals');
    }
};
