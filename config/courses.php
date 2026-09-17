<?php

/*
|--------------------------------------------------------------------------
| Course Configuration (spec 4.5.6)
|--------------------------------------------------------------------------
|
| Depth is f(skill_level, plan): how *much* course a student gets. Skill level
| decides what is taught and how; the plan decides nothing at all in v1, and
| billing is an explicit WONT (ledger D11).
|
| SKILL LEVEL IS NOT A PRICE TIER. 101 is longer than 301 here on purpose --
| beginners need more scaffolding, and a shorter advanced course is not a
| worse one. Any future depth-gating enters through `plan`, never through
| skill level (founder correction, recorded in docs/02-design.md).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | The set of values `users.plan` may hold. `standard` is every invited
    | account; `demo` is minted only by the public demo route, and may take
    | lesson 1 and no further until it signs up (spec 12, ledger D50).
    |
    | These stay config keys until a plan carries its first real attribute --
    | a price, a quota, anything a row can hold that a key cannot. That is the
    | moment this becomes a `plans` table and `users.plan` becomes a foreign
    | key (ledger D11). It is deferred on schedule, not on principle.
    |
    */

    'default_plan' => 'standard',

    'plans' => ['standard', 'demo'],

    /*
    | How many days a `demo`-plan account is kept unclaimed before
    | `demo:sweep` deletes it (spec 12.5.1). Conversion moves the row to
    | `standard`, which is what takes it out of the sweep's reach.
    */

    'demo_account_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Depth
    |--------------------------------------------------------------------------
    |
    | Keyed depth.{skill_level}.{plan}. Rendered into the `depth_params`
    | prompt variable as a markdown key list (spec 4.5.6.2) and handed to
    | `syllabus.generate` / `syllabus.revise`, which are told these set the
    | size of the course and nothing else.
    |
    */

    'depth' => array_map(
        // A demo is lesson 1 of an ordinary course, so it draws the same
        // syllabus; signing up only opens the lessons after it (spec 12.2,
        // ledger D50 as amended 2026-09-17).
        fn (array $depth): array => ['standard' => $depth, 'demo' => $depth],
        [
            101 => [
                'lessons' => [8, 10],
                'sections_per_lesson' => [3, 4],
                'minutes_per_lesson' => 20,
            ],

            201 => [
                'lessons' => [6, 8],
                'sections_per_lesson' => [3, 4],
                'minutes_per_lesson' => 25,
            ],

            301 => [
                'lessons' => [5, 7],
                'sections_per_lesson' => [4, 5],
                'minutes_per_lesson' => 30,
            ],
        ],
    ),

    /*
    |--------------------------------------------------------------------------
    | Syllabus Revisions
    |--------------------------------------------------------------------------
    |
    | How many times a student may send the syllabus back before they have to
    | approve it or start over (spec 4.5.4). Generation 1 is the first draft,
    | so the highest generation that can exist is this plus one.
    |
    | Not keyed by plan, and not a CHECK constraint on the table: it is policy,
    | and freezing policy into schema is what ledger D7c exists to undo.
    |
    */

    'max_syllabus_revisions' => 3,

];
