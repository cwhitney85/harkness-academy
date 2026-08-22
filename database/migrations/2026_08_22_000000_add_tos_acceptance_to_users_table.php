<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the moment a user attested to being 18 or older and accepted the
     * Terms of Service (spec 1.2). Null is impossible for accounts created
     * through registration; the column is nullable only so the migration can
     * run against pre-existing rows.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('tos_accepted_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tos_accepted_at');
        });
    }
};
