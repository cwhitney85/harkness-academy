<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The lesson transcript (spec 5.2.6, 5.3) -- the table ledger D9 deferred.
 *
 * D9 refused to build this alongside `interview_messages` because the two
 * have opposite retention rules and package 4 did not yet know what replay,
 * reconnect and message ids needed. It now does, and they are these three
 * columns: `turn_id`, `position`, and the partial unique index below.
 *
 * **Kept, not discarded.** An interview transcript is garbage the moment its
 * document is written; a lesson transcript is what 5.3 resumes from and what
 * 5.4.2 summarizes, and it outlives both.
 *
 * `turn_id` is the identity the SSE vocabulary streams under (5.2.5). It is
 * minted before the first byte and written with the row at `turn.end`, so a
 * client that reconnects can tell a turn that completed from one that did not.
 *
 * A row exists only for a turn that finished. A stream killed mid-section
 * writes nothing, which is what makes regeneration safe: sections are
 * code-paced, so re-teaching an unfinished one is idempotent (5.2.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_messages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();

            // The SSE turn this row is the completed form of (5.2.5). Unique
            // across the table: a replayed `turn.end` must name the same turn
            // the client was already watching, and two rows sharing an id
            // would make that ambiguous.
            $table->uuid('turn_id')->unique();

            // `section` (taught prose) or `qa` (a question mid-section).
            // `interview` and `explainer` are turn kinds on the wire but never
            // rows here: interviews live in `interview_messages` and the
            // explainer is stateless by contract (5.5).
            $table->string('kind', 16);

            $table->string('speaker', 16);

            // Which section the turn belongs to, 1-based. Non-null: every turn
            // in a lesson happens inside a section, including Q&A.
            $table->unsignedSmallInteger('section_index');

            $table->text('content');

            // Order of speaking within the lesson.
            $table->unsignedSmallInteger('position');

            $table->timestamp('created_at');

            $table->unique(['lesson_id', 'position'], 'lesson_messages_one_turn_per_position');

            $table->index(['lesson_id', 'section_index'], 'lesson_messages_by_section');
        });

        // 5.2.6's done-condition made structural: "exactly one message row"
        // for a taught section, however many times a client reconnects to it.
        // Two workers racing to persist the same section collide here rather
        // than doubling the transcript the summarizer reads.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX lesson_messages_one_taught_section
                ON lesson_messages (lesson_id, section_index)
                WHERE kind = 'section' AND speaker = 'tutor'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_messages');
    }
};
