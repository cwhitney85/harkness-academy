<?php

/*
|--------------------------------------------------------------------------
| Lesson Engine Configuration (spec 5.4)
|--------------------------------------------------------------------------
|
| The completion pipeline's timing policy. Policy, not schema: these are the
| numbers most likely to be wrong on first contact with real students, and
| they are here so changing them is a config edit rather than a deploy of new
| code (ledger D7c's lesson -- freezing policy into structure is what needs
| undoing later).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | Spec 5.4.5: the chain retries three times with backoff, then parks with
    | an alert. Seconds between attempts, one entry per gap.
    |
    */

    'pipeline' => [

        'tries' => 3,

        'backoff' => [10, 60, 300],

    ],

    /*
    |--------------------------------------------------------------------------
    | Unlock Escape Hatch (spec 5.4.6)
    |--------------------------------------------------------------------------
    |
    | The next lesson normally unlocks when the chain finishes, so its
    | bootstrap reads an up-to-date WORKING_KNOWLEDGE document. If the chain
    | parks, this is how long the student waits before the next lesson opens
    | anyway: a stale WK beats a stuck course.
    |
    | Measured from `lessons.pipeline_parked_at`, not from completion -- the
    | retries in `pipeline.backoff` already take minutes, and starting the
    | clock at completion would open the next lesson while the chain was still
    | legitimately working.
    |
    */

    'unlock_escape_hatch_minutes' => 15,

];
