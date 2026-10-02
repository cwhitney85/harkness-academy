<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every document belongs to something, and every version names the course
 * that wrote it (spec 16.3.1, ledger D58 as amended by D69).
 *
 * Working knowledge belongs to its subtopic row (D8); criteria, guidance and
 * summaries to their course. Those are the only two: the module rollup is
 * rendered on read and never stored (3.3). Neither column may be empty, so no
 * reader has to guess what an absent owner means.
 *
 * Backfilled from the paths. A level folder shared by several attempts (D7b's
 * rider) goes to the highest, the last that could write there; a version goes
 * to the newest matching course that existed when it was written. A document
 * or version with no course to match — its course deleted by hand before
 * nothing was hard-deleted (D68) — refuses the migration and is listed, so a
 * person decides what becomes of it.
 */
return new class extends Migration
{
    private const COURSE = 'App\Models\Course';

    private const SUBTOPIC = 'App\Models\Subtopic';

    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table): void {
            $table->nullableMorphs('owner');
        });

        Schema::table('memory_versions', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::update(<<<'SQL'
            UPDATE memories m SET owner_type = ?, owner_id = s.id
            FROM modules mo JOIN subtopics s ON s.module_id = mo.id
            WHERE m.path = mo.slug || '/' || s.slug || '/WORKING_KNOWLEDGE.md'
        SQL, [self::SUBTOPIC]);

        DB::update(<<<'SQL'
            UPDATE memories m SET owner_type = ?, owner_id = c.id
            FROM (
                SELECT DISTINCT ON (user_id, module_slug, subtopic_slug, skill_level)
                    id, user_id, module_slug || '/' || subtopic_slug || '/' || skill_level || '/' AS prefix
                FROM courses
                ORDER BY user_id, module_slug, subtopic_slug, skill_level, attempt DESC
            ) c
            WHERE m.owner_id IS NULL AND c.user_id = m.user_id AND starts_with(m.path, c.prefix)
        SQL, [self::COURSE]);

        DB::update(<<<'SQL'
            UPDATE memory_versions v SET course_id = (
                SELECT c.id FROM memories m JOIN courses c ON c.user_id = m.user_id
                WHERE m.id = v.memory_id
                  AND starts_with(m.path, c.module_slug || '/' || c.subtopic_slug || '/')
                  AND (m.owner_type = ? OR starts_with(m.path, c.module_slug || '/' || c.subtopic_slug || '/' || c.skill_level || '/'))
                  AND c.created_at <= v.created_at
                ORDER BY c.created_at DESC, c.id DESC
                LIMIT 1
            )
        SQL, [self::SUBTOPIC]);

        $unowned = DB::select(<<<'SQL'
            SELECT DISTINCT m.id, m.user_id, m.path FROM memories m
            LEFT JOIN memory_versions v ON v.memory_id = m.id
            WHERE m.owner_id IS NULL OR v.course_id IS NULL
            ORDER BY m.id
        SQL);

        if ($unowned !== []) {
            $list = implode("\n", array_map(fn ($row) => "  memories #{$row->id} (user {$row->user_id}) {$row->path}", $unowned));

            throw new RuntimeException("These documents have no course to belong to or to have been written by. Decide what becomes of them, then migrate again:\n{$list}");
        }

        Schema::table('memories', function (Blueprint $table): void {
            $table->string('owner_type')->nullable(false)->change();
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
        });

        Schema::table('memory_versions', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable(false)->change();
        });

        DB::statement(sprintf(
            "ALTER TABLE memories ADD CONSTRAINT memories_owner_type CHECK (owner_type IN ('%s', '%s'))",
            self::COURSE,
            self::SUBTOPIC,
        ));
    }

    public function down(): void
    {
        Schema::table('memory_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
        });

        Schema::table('memories', function (Blueprint $table): void {
            $table->dropMorphs('owner');
        });
    }
};
