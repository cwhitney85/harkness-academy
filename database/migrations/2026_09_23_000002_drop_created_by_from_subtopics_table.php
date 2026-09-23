<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `subtopics.created_by` is dropped (ledger D68, superseding D67).
 *
 * A student subtopic is created in the same transaction as its first course,
 * so who typed it first is the student on its oldest course -- a fact the
 * courses table already owns. Kept, it was a second copy of that fact and a
 * NULL that meant "catalog".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE subtopics
                DROP CONSTRAINT subtopics_student_has_creator,
                DROP COLUMN created_by
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subtopics ADD COLUMN created_by bigint');

        DB::statement(<<<'SQL'
            UPDATE subtopics s SET created_by = (
                SELECT c.user_id FROM courses c WHERE c.subtopic_id = s.id ORDER BY c.created_at, c.id LIMIT 1
            )
            WHERE s.origin = 'student'
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE subtopics ADD CONSTRAINT subtopics_student_has_creator
                CHECK ((origin = 'student') = (created_by IS NOT NULL))
        SQL);
    }
};
