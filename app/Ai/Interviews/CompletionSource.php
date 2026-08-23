<?php

namespace App\Ai\Interviews;

/**
 * Who ended the interview (spec 2.3.4 / decision R3).
 */
enum CompletionSource: string
{
    /** The model called `signal_complete`. */
    case Model = 'model';

    /** The student pressed the always-visible Done button. */
    case Student = 'student';
}
