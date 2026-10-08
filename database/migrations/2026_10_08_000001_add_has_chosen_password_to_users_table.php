<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the student knows their own password (spec 18.4.1, ledger D74).
 *
 * A Google sign-up and a demo row carry a random one nobody was shown, so
 * security settings offer Set a password instead of asking for the current
 * one. Every `standard` row that exists today registered with a password.
 * Appended last: Postgres cannot place an added column anywhere else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_chosen_password')->default(true);
        });

        DB::table('users')->where('plan', 'demo')->update(['has_chosen_password' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('has_chosen_password');
        });
    }
};
