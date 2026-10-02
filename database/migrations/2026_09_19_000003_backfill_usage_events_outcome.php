<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rows written before 14.3.1 get an outcome (spec 14.3.2). v1 recorded a
 * failed call as a row with no input and no output tokens, so that is what
 * `failed` means here; every other old row served.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('usage_events')->whereNull('outcome')->update([
            'outcome' => DB::raw("case when input_tokens = 0 and output_tokens = 0 then 'failed' else 'ok' end"),
        ]);
    }

    public function down(): void
    {
        // The backfilled rows cannot be told from rows written with an outcome.
    }
};
