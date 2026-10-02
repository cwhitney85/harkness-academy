<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A removal is a record, and every row it hides carries its mark (spec
 * 16.2.1, ledger D57).
 *
 * Who removed what, and why, is the `admin_actions` row the removal points
 * at. Each hidden row is stamped with `deleted_at` and the removal that hid
 * it, so restoring one removal unstamps exactly its own rows: a course removed
 * on its own keeps its own mark through its subtopic's removal and restore.
 * The two columns are set and cleared together.
 *
 * Restricts deletion throughout: nothing here is ever hard-deleted (D68).
 */
return new class extends Migration
{
    private const TABLES = ['modules', 'subtopics', 'courses', 'memories'];

    public function up(): void
    {
        Schema::create('removals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_action_id')->unique()->constrained()->restrictOnDelete();
        });

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('removal_id')->nullable()->constrained()->restrictOnDelete();
                $table->softDeletes();
            });

            DB::statement("ALTER TABLE {$name} ADD CONSTRAINT {$name}_removed_by_a_removal CHECK ((deleted_at IS NULL) = (removal_id IS NULL))");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropSoftDeletes();
                $table->dropConstrainedForeignId('removal_id');
            });
        }

        Schema::dropIfExists('removals');
    }
};
