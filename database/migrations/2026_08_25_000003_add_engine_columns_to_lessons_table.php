<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What package 5 needs a lesson row to remember (spec 5.1, 5.3, 5.4).
 *
 * Package 4 created `lessons` with exactly what D10.5 listed -- position,
 * status, guidance_path -- and left the lifecycle to package 5 on purpose.
 * These are that lifecycle.
 *
 * `section_index` is 1-based, like `position`. Every index in the engine is:
 * the wire (`/sections/{index}/stream`), the SSE payloads, the checkpoint
 * rows and the `lesson.section` prompt variable all count sections from one,
 * so no layer has to remember which side of an off-by-one it is on.
 *
 * `completed_at` is the irrevocable half of 5.4.1: it is stamped
 * synchronously when the student finishes, before the summarization chain is
 * dispatched, so nothing the chain does or fails to do can take the
 * completion back (5.4.5).
 *
 * `summary_json` is the `json/lesson-summary.v1` source of record (5.4.3).
 * `{lesson}/SUMMARY.md` in the memory tree is rendered from it, so the
 * document and the structure cannot disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            // The section the student is on, 1-based. Null until the lesson
            // is first opened -- an unopened lesson is not "on section 1",
            // and 5.3 restores this exact value.
            $table->unsignedSmallInteger('section_index')->nullable()->after('status');

            $table->timestamp('started_at')->nullable()->after('guidance_path');

            // Stamped synchronously and never cleared (5.4.1).
            $table->timestamp('completed_at')->nullable()->after('started_at');

            $table->jsonb('summary_json')->nullable()->after('completed_at');

            // When the completion chain gave up after its retries (5.4.5).
            // The escape hatch of 5.4.6 measures its wait from here.
            $table->timestamp('pipeline_parked_at')->nullable()->after('summary_json');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropColumn([
                'section_index',
                'started_at',
                'completed_at',
                'summary_json',
                'pipeline_parked_at',
            ]);
        });
    }
};
