<?php

/*
|--------------------------------------------------------------------------
| Assessment Configuration (spec 6.3, 6.4)
|--------------------------------------------------------------------------
|
| Policy, not schema. The pass mark in particular is the number most likely to
| be wrong on first contact with real students, and it is here so moving it is
| a config edit rather than a deploy (the same reasoning as config/lessons.php).
|
*/

return [

    'final_exam' => [

        /*
        | The fraction of an exam a student has to get right to pass. It gates
        | `completed`, so it is the one number in the system that can end a
        | course -- and the one most likely to need moving once real students
        | sit real exams.
        */
        'pass_mark' => 0.7,

        /*
        | Generation attempts before giving up (spec 4.5.1's shape: one retry,
        | then fail). The exam fails **closed**: a student who cannot generate
        | an exam is held at the end of their course and can try again, and
        | nothing they have done is lost. Serving a half-scoped exam instead
        | would put the gate itself in doubt.
        */
        'generation_attempts' => 2,

    ],

    'refresher' => [

        /*
        | Generation attempts for a refresher quiz. Same shape, lower stakes:
        | a refresher gates nothing.
        */
        'generation_attempts' => 2,

    ],

];
