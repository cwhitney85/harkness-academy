<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The model catalog and the tiers over it (spec 14.1.1, 14.1.2, ledger D52,
 * D53). The one file outside tests allowed to name a model (14.1.4).
 *
 * `ai_models.provider` is the laravel/ai driver key. Prices are US dollars
 * per million tokens, and a price belongs to the model rather than to a tier,
 * so a call is costed by whatever model actually served it.
 *
 * A tier is a primary model, an ordered list of backups the SDK fails over
 * to, and a timeout in seconds. The timeout is the tier's for the reason it
 * always was: laravel/ai defaults every call to 60, which no `top` structured
 * generation can finish inside (found on the first live run, 2026-09-02).
 *
 * Seeded with what config/ai.php held on 2026-09-19: three models at its
 * prices -- cache reads at 0.1x input, five-minute cache writes at 1.25x, as
 * its multipliers made them -- and its tier map, with no backups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->string('model');
            $table->string('display_name');
            $table->boolean('active')->default(true);
            $table->decimal('input_per_mtok', 10, 4);
            $table->decimal('output_per_mtok', 10, 4);
            $table->decimal('cache_read_per_mtok', 10, 4);
            $table->decimal('cache_write_per_mtok', 10, 4);
            $table->timestamps();

            $table->unique(['provider', 'model']);
        });

        Schema::create('ai_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 16)->unique();
            $table->foreignId('ai_model_id')->constrained('ai_models')->restrictOnDelete();
            $table->jsonb('backup_model_ids')->default('[]');
            $table->unsignedSmallInteger('timeout');
            $table->timestamps();
        });

        DB::table('ai_models')->insert(array_map(fn (array $row): array => $row + [
            'provider' => 'anthropic',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], [
            ['model' => 'claude-haiku-4-5', 'display_name' => 'Claude Haiku 4.5', 'input_per_mtok' => 1.00, 'output_per_mtok' => 5.00, 'cache_read_per_mtok' => 0.10, 'cache_write_per_mtok' => 1.25],
            ['model' => 'claude-sonnet-5', 'display_name' => 'Claude Sonnet 5', 'input_per_mtok' => 3.00, 'output_per_mtok' => 15.00, 'cache_read_per_mtok' => 0.30, 'cache_write_per_mtok' => 3.75],
            ['model' => 'claude-opus-5', 'display_name' => 'Claude Opus 5', 'input_per_mtok' => 5.00, 'output_per_mtok' => 25.00, 'cache_read_per_mtok' => 0.50, 'cache_write_per_mtok' => 6.25],
        ]));

        $model = fn (string $id): int => DB::table('ai_models')->where('model', $id)->value('id');

        DB::table('ai_tiers')->insert([
            ['name' => 'cheap', 'ai_model_id' => $model('claude-haiku-4-5'), 'timeout' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'mid', 'ai_model_id' => $model('claude-sonnet-5'), 'timeout' => 120, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'top', 'ai_model_id' => $model('claude-opus-5'), 'timeout' => 300, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tiers');
        Schema::dropIfExists('ai_models');
    }
};
