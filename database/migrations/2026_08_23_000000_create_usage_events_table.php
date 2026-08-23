<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usage instrumentation (spec 2.2): one row per AI call, enough to produce a
 * per-feature cost report from SQL alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What asked for the call, and which inventory row it used.
            $table->string('feature');
            $table->string('prompt_key');

            // What served it. `tier` is the vocabulary; `model` is the receipt.
            $table->string('provider');
            $table->string('tier');
            $table->string('model');

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            $table->unsignedInteger('latency_ms');

            $table->decimal('cost_usd', 12, 8)->default(0);

            $table->timestamp('created_at');

            $table->index(['feature', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
