<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The preset catalog (spec 4.1): what the course picker offers.
 *
 * These tables are *suggestions*, not identity. A course stores its own
 * module and subtopic slugs (see the courses migration) and never points
 * back here, so renaming a catalog entry -- which an admin UI will do
 * post-v1 -- changes what new students are offered and nothing else. If a
 * course derived its memory paths from these rows, renaming "Algebra" would
 * silently relocate every existing student's WORKING_KNOWLEDGE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();

            // The first segment of every memory path under this module.
            $table->string('slug')->unique();

            $table->string('title');

            $table->unsignedSmallInteger('position');

            $table->timestamps();
        });

        Schema::create('subtopics', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('module_id')->constrained()->cascadeOnDelete();

            $table->string('slug');

            $table->string('title');

            $table->unsignedSmallInteger('position');

            $table->timestamps();

            // Two catalog entries in one module may not collapse to one folder.
            $table->unique(['module_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtopics');
        Schema::dropIfExists('modules');
    }
};
