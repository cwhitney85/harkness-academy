<?php

use App\Courses\GenerationState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A generation request is kept once it is over, and its state is a column
 * (spec 16.2.11, ledger D68 amending D25).
 *
 * The one-row-per-(course, kind) key becomes one *open* row, so a double press
 * still costs nothing while every request a course ever made stays. Rows that
 * exist now are either still out or failed: until now a landed one was deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->string('state')->default(GenerationState::Open->value);
        });

        DB::update("UPDATE generation_requests SET state = 'failed' WHERE failed_at IS NOT NULL");

        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->string('state')->default(null)->change();
            $table->dropUnique('generation_requests_one_per_kind');
        });

        $states = implode(', ', array_map(fn (GenerationState $state) => "'{$state->value}'", GenerationState::cases()));

        DB::statement("ALTER TABLE generation_requests ADD CONSTRAINT generation_requests_state CHECK (state IN ({$states}))");
        DB::statement("ALTER TABLE generation_requests ADD CONSTRAINT generation_requests_failure_when_failed CHECK ((state = 'failed') = (failed_at IS NOT NULL AND failure IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX generation_requests_one_open_per_kind ON generation_requests (course_id, kind) WHERE state = 'open'");
    }

    public function down(): void
    {
        // Refuses, rather than deletes, once a course has kept more than one.
        DB::statement('DROP INDEX generation_requests_one_open_per_kind');

        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn('state');
            $table->unique(['course_id', 'kind'], 'generation_requests_one_per_kind');
        });
    }
};
