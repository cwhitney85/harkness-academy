<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a person may administer (spec 13.1.1, ledger D61).
 *
 * Only adds: every existing account reads `student`, including Colin's until
 * `user:role` says otherwise (13.1.3). Role never reads plan or skill level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 16)->default('student');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
