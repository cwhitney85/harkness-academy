<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fuller usage row (spec 14.3.1, ledger D56). Additive: every existing row
 * survives with these null or zero. `acting_user_id` landed with 13.4.3.
 *
 * `prompt_version_id` and `experiment_variant_id` carry no foreign key yet --
 * their tables arrive with package 15, which adds the constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_events', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained('ai_models')->restrictOnDelete();
            $table->unsignedBigInteger('prompt_version_id')->nullable();
            $table->unsignedBigInteger('experiment_variant_id')->nullable();
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->string('outcome', 16)->nullable();
            $table->string('plan', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
            $table->dropConstrainedForeignId('lesson_id');
            $table->dropConstrainedForeignId('ai_model_id');
            $table->dropColumn(['prompt_version_id', 'experiment_variant_id', 'reasoning_tokens', 'outcome', 'plan']);
        });
    }
};
