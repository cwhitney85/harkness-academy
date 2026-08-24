<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The memory tree (spec 3.1, ledger D3a): a virtual file tree in Postgres.
 *
 * A row is a file: one user's document at one path. `memory_versions` keeps
 * the content written by every `put`, so history survives overwrites -- the
 * tree is the model's long-term record and nothing in it is silently lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // A path, not a filesystem location: `math/algebra/WORKING_KNOWLEDGE.md`.
            $table->string('path');

            $table->text('content');

            $table->timestamps();

            // One document per path per user -- the uniqueness the path API relies on.
            $table->unique(['user_id', 'path']);
        });

        Schema::create('memory_versions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('memory_id')->constrained()->cascadeOnDelete();

            // Monotonic per memory; version 1 is the content the file was created with.
            $table->unsignedInteger('version');

            $table->text('content');

            $table->timestamp('created_at');

            $table->unique(['memory_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_versions');
        Schema::dropIfExists('memories');
    }
};
