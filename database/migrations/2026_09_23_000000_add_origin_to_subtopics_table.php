<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One subtopics table for catalog and student subtopics (spec 16.1.1, ledger
 * D59).
 *
 * Every row that exists now was seeded, so it becomes `catalog`, listed. The
 * defaults that say so are dropped once they have: an insert has to name its
 * origin and whether it is listed, so a student subtopic cannot reach the
 * picker by leaving a column out.
 *
 * `created_by` is the student who first typed the name. It carries no foreign
 * key, as `admin_actions.subject_id` does not: a student subtopic is shared by
 * everyone who types that name, so it can neither cascade with its creator's
 * account nor block that account's deletion, and nulling it would make NULL
 * mean "catalog" and "creator gone" at once. NULL means catalog and nothing
 * else, and the check below holds it to that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtopics', function (Blueprint $table): void {
            $table->string('origin', 16)->default('catalog');
            $table->boolean('listed')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE subtopics
                ALTER COLUMN origin DROP DEFAULT,
                ALTER COLUMN listed DROP DEFAULT,
                ADD CONSTRAINT subtopics_origin CHECK (origin IN ('catalog', 'student')),
                ADD CONSTRAINT subtopics_student_has_creator CHECK ((origin = 'student') = (created_by IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        // Before this migration there was no such thing as a student subtopic,
        // and the picker listed every row.
        DB::table('subtopics')->where('origin', 'student')->delete();

        Schema::table('subtopics', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'listed', 'created_by']);
        });
    }
};
