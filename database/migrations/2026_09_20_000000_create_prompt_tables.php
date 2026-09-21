<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The prompt registry (spec 15.1.1, ledger D65): a file seeds a prompt and
 * archives it, and the database owns what runs.
 *
 * `prompts` holds the contract the calling code depends on -- `output` and
 * `vars` -- which comes only from the file. `prompt_versions` holds what the
 * editor changes: the body and the tier. A version is never updated, so it
 * has a `created_at` and no `updated_at`.
 *
 * The active version is a pointer on the prompt rather than a flag on the
 * version, so a prompt cannot have two actives. It is null only inside the
 * transaction that creates a prompt and its first version.
 *
 * `usage_events.prompt_version_id` has been waiting for this table since
 * 14.3.1; it gets its constraint here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('output', 64);
            $table->jsonb('vars')->default('[]');
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('prompt_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->text('body');
            $table->string('tier', 16);
            $table->string('source', 8);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_hash', 64)->nullable();
            $table->timestamp('created_at');

            $table->unique(['prompt_id', 'number']);
        });

        Schema::table('prompts', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('prompt_versions')->restrictOnDelete();
        });

        Schema::table('usage_events', function (Blueprint $table): void {
            $table->foreign('prompt_version_id')->references('id')->on('prompt_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table): void {
            $table->dropForeign(['prompt_version_id']);
        });

        Schema::table('prompts', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('prompt_versions');
        Schema::dropIfExists('prompts');
    }
};
