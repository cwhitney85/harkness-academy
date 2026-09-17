<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Spec 5.4.6: open the next lesson when a completion chain has been parked
 * longer than `lessons.unlock_escape_hatch_minutes`. A stale working-knowledge
 * document beats a stuck course.
 */
Schedule::command('lessons:unlock-stalled')->everyFiveMinutes();

/*
 * Spec 12.5.1: a demo link can be followed by a crawler as easily as a person,
 * so unclaimed demo accounts are cleared out once they are old enough.
 */
Schedule::command('demo:sweep')->daily();
