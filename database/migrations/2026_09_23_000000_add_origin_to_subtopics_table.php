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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtopics', function (Blueprint $table): void {
            $table->string('origin', 16)->default('catalog');
            $table->boolean('listed')->default(true);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE subtopics
                ALTER COLUMN origin DROP DEFAULT,
                ALTER COLUMN listed DROP DEFAULT,
                ADD CONSTRAINT subtopics_origin CHECK (origin IN ('catalog', 'student'))
        SQL);
    }

    public function down(): void
    {
        // Before this migration there was no such thing as a student subtopic,
        // and the picker listed every row.
        DB::table('subtopics')->where('origin', 'student')->delete();

        Schema::table('subtopics', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'listed']);
        });
    }
};
