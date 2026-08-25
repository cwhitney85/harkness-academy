<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lessons an approved syllabus becomes (spec 4.5.5.1, ledger D7/D10.5).
 *
 * Exactly what D7 listed and nothing more: position, status, guidance_path.
 *
 * No title, objective or slug column. Those live in the approved syllabus
 * JSON, which is also what the student's syllabus view renders from -- one
 * source, so the two cannot disagree, and a lesson list reads them from there.
 * D7's rule decides it: the app queries position and status, and the model
 * reads the material.
 *
 * `guidance_path` points at a document in the memory tree and carries no
 * attempt number, which is why only the highest attempt at a level may be
 * resumed (ledger D7b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();

            // 1-based teaching order, taken from the syllabus.
            $table->unsignedSmallInteger('position');

            $table->string('status', 32);

            // Sized to match `memories.path`.
            $table->string('guidance_path', 255);

            $table->timestamps();

            $table->unique(['course_id', 'position'], 'lessons_one_lesson_per_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
