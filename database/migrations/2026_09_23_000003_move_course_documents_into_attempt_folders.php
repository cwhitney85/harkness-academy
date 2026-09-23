<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Each attempt's documents move into their own folder (spec 16.3.2, ledger
 * D58): `{module}/{subtopic}/{level}/…` becomes `…/{level}/a{attempt}/…`.
 *
 * Moves the course-owned documents 16.3.1 attached, and the guidance path each
 * lesson stores. Working knowledge stays where it is, because it is the
 * subtopic's. Only `path` changes: every version keeps its id, so the
 * versions `course_syllabi` pinned still resolve.
 *
 * Checked before anything moves: a path the new folder would push past the
 * 255-character column refuses the migration and is listed.
 *
 * Take a restore point first (docs/deploy.md).
 */
return new class extends Migration
{
    private const LEVEL = "c.module_slug || '/' || c.subtopic_slug || '/' || c.skill_level";

    private const ATTEMPT = self::LEVEL." || '/a' || c.attempt";

    public function up(): void
    {
        $tooLong = DB::select('SELECT m.id, '.self::moved('m.path').' AS path FROM memories m JOIN courses c ON c.id = m.owner_id'
            ." WHERE m.owner_type = 'App\\Models\\Course' AND length(".self::moved('m.path').') > 255');

        if ($tooLong !== []) {
            $list = implode("\n", array_map(fn ($row) => "  memories #{$row->id} {$row->path}", $tooLong));

            throw new RuntimeException("These documents would be longer than 255 characters in their attempt folder:\n{$list}");
        }

        DB::update('UPDATE memories m SET path = '.self::moved('m.path').' FROM courses c'
            ." WHERE m.owner_type = 'App\\Models\\Course' AND c.id = m.owner_id");

        DB::update('UPDATE lessons l SET guidance_path = '.self::moved('l.guidance_path').' FROM courses c WHERE c.id = l.course_id');
    }

    public function down(): void
    {
        DB::update('UPDATE memories m SET path = '.self::LEVEL.' || substr(m.path, length('.self::ATTEMPT.') + 1) FROM courses c'
            ." WHERE m.owner_type = 'App\\Models\\Course' AND c.id = m.owner_id");

        DB::update('UPDATE lessons l SET guidance_path = '.self::LEVEL.' || substr(l.guidance_path, length('.self::ATTEMPT.') + 1) FROM courses c WHERE c.id = l.course_id');
    }

    /**
     * `{level}/rest` → `{level}/a{attempt}/rest`, for a column joined to its course as `c`.
     */
    private static function moved(string $column): string
    {
        return self::ATTEMPT." || substr({$column}, length(".self::LEVEL.') + 1)';
    }
};
