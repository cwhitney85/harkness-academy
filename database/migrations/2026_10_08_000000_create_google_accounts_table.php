<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Google sign-in connected to an account (spec 18.2.2, ledger D73).
 *
 * Keyed on Google's `sub`, which never changes, rather than its email, which
 * can (18.2.3). Both sides are unique: an account has at most one Google
 * account, and a Google account at most one account. A table rather than a
 * nullable column on `users`, so no row has to say "not connected" with NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sub')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_accounts');
    }
};
