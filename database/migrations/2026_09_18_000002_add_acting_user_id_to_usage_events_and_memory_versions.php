<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin acting as a student, on every call and every memory write made
 * while they did (spec 13.4.3, ledger D60).
 *
 * Owned by 14.3.1 (`usage_events`) and 16.3.1 (`memory_versions`), which list
 * the rest of each row's new columns; added here because 13.4.3 stamps them
 * and both packages open only after 13 lands. Null for everything a student
 * did themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['usage_events', 'memory_versions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('acting_user_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['usage_events', 'memory_versions'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('acting_user_id');
            });
        }
    }
};
