<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every course points at its subtopic row (spec 16.1.3, ledger D59).
 *
 * Beside its frozen slugs, not instead of them: the slugs are the course's
 * memory path, and retitling or promoting a subtopic must move nothing.
 *
 * A slug the catalog does not have was typed by a student before student rows
 * existed. It gets one now, credited to the first student who typed it and
 * titled as they wrote it, and is printed so it can be read over: whether any
 * of them is a catalog subtopic under an old name is a question only a person
 * can answer.
 *
 * Restricts deletion: a subtopic is removed, never deleted (16.2.9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('subtopic_id')->nullable()->constrained()->restrictOnDelete();
        });

        $created = DB::select(<<<'SQL'
            INSERT INTO subtopics (module_id, slug, title, position, origin, listed, created_by, created_at, updated_at)
            SELECT DISTINCT ON (m.id, c.subtopic_slug)
                m.id, c.subtopic_slug, c.subtopic_title,
                (SELECT coalesce(max(position), 0) FROM subtopics WHERE module_id = m.id)
                    + dense_rank() OVER (PARTITION BY m.id ORDER BY c.subtopic_slug),
                'student', false, c.user_id, now(), now()
            FROM courses c
            JOIN modules m ON m.slug = c.module_slug
            WHERE NOT EXISTS (
                SELECT 1 FROM subtopics s WHERE s.module_id = m.id AND s.slug = c.subtopic_slug
            )
            ORDER BY m.id, c.subtopic_slug, c.created_at, c.id
            RETURNING module_id, slug, title, created_by
        SQL);

        DB::statement(<<<'SQL'
            UPDATE courses c SET subtopic_id = s.id
            FROM modules m, subtopics s
            WHERE m.slug = c.module_slug AND s.module_id = m.id AND s.slug = c.subtopic_slug
        SQL);

        // A course whose module is not in `modules` has no row to point at,
        // and the NOT NULL below refuses the migration rather than guess one.
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('subtopic_id')->nullable(false)->change();
        });

        foreach ($created as $row) {
            echo "  student subtopic created: module #{$row->module_id} / {$row->slug} «{$row->title}», first typed by user #{$row->created_by}\n";
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subtopic_id');
        });
    }
};
