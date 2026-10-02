<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student who deletes their account is soft-deleted (spec 16.2.10, ledger
 * D68). Nothing under the row is touched, the account cannot sign in, and its
 * email stays taken. An admin can bring it back. Only an unclaimed demo is
 * still hard-deleted, by the sweep (12.5.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
