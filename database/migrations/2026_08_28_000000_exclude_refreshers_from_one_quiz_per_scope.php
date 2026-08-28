<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A scope may hold any number of refreshers (spec 6.4).
 *
 * `quizzes_one_per_scope` was written for the three kinds that are generated
 * once and re-served: a section gets one checkpoint, a lesson one homework, a
 * course one final exam. Package 5 wrote two of those kinds and had no reason
 * to think about the third case (ledger D12 left the refresher's shape to 6.4
 * on purpose).
 *
 * A refresher is the opposite of one-per-scope. Its whole point is coming back
 * to the same lesson or subtopic weeks later and being asked about it again;
 * under the old index the second visit would re-serve the first visit's
 * questions, which is exactly the "system has lost track of me" failure the
 * refresher prompt is written to avoid.
 *
 * A partial index rather than a new column, because there is no second fact
 * to store -- the kind already says whether the scope is exclusive. The
 * predicate mirrors `QuizKind::isRepeatable()`; changing one without the other
 * is what `QuizServiceTest` pins.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX quizzes_one_per_scope');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quizzes_one_per_scope
                ON quizzes (quizzable_type, quizzable_id, kind, (COALESCE(section_index, -1)))
                WHERE kind <> 'refresher'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX quizzes_one_per_scope');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quizzes_one_per_scope
                ON quizzes (quizzable_type, quizzable_id, kind, (COALESCE(section_index, -1)))
        SQL);
    }
};
