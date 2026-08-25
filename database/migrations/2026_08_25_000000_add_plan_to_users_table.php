<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which plan a student is on (spec 4.5.6.1, ledger D11).
 *
 * A string, not a foreign key, and in v1 one value with no price, quota or
 * entitlement behind it -- the same shape `courses.style` ships with (R1).
 * Billing is a v1 WONT; this column exists because depth is f(skill_level,
 * plan) and the second argument has to come from somewhere.
 *
 * It becomes a foreign key to a `plans` table the moment a plan carries an
 * attribute a config key cannot hold. That trigger is named in D11.
 *
 * The default below is duplicated from `config/courses.php` because a schema
 * default is baked in at migrate time and cannot read config later.
 * CoursesConfigTest pins the two together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('plan', 32)->default('standard');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('plan');
        });
    }
};
