<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin action log (spec 13.2.1): one row per admin write, and nothing
 * else in the admin unit keeps an audit of its own.
 *
 * Append-only, and enforced here rather than by convention: a trigger refuses
 * every UPDATE and DELETE, so no code path -- Eloquent, the query builder or a
 * hand-typed statement -- can rewrite what an admin did. That is also why the
 * actor restricts deletion rather than cascading or nulling: either would be
 * a write to this table.
 *
 * The subject is polymorphic and carries no foreign key, so a row still says
 * what it was about after its subject is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->text('reason')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION admin_actions_append_only() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'admin_actions is append-only (spec 13.2.1)';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER admin_actions_append_only
                BEFORE UPDATE OR DELETE ON admin_actions
                FOR EACH ROW EXECUTE FUNCTION admin_actions_append_only();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
        DB::unprepared('DROP FUNCTION IF EXISTS admin_actions_append_only()');
    }
};
