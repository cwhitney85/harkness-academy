<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The model a lesson's session started on (spec 14.1.6; D10.5's 2026-09-19
 * note). A `tier: session` prompt runs on it for the rest of the lesson, so
 * moving a tier to another model never switches a lesson mid-way and throws
 * away its cached prefix. Null until the session first calls a model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->foreignId('ai_model_id')->nullable()->after('started_at')->constrained('ai_models')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_model_id');
        });
    }
};
