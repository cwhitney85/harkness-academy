<?php

namespace App\Lessons;

/**
 * How well one concept landed in one lesson (spec 5.4.2).
 *
 * Three values, not a score. A number invites arithmetic -- averaging two
 * lessons' worth of a concept into 0.65 -- and there is nothing to average:
 * the summarizer is reading evidence, not measuring.
 *
 * The same three words `wk.update` sorts the working-knowledge document into
 * (`## Solid`, `## Shaky`, `## Gaps`), which is what lets a summary flow into
 * a WK revision without a translation step in between.
 */
enum Mastery: string
{
    /** Used it correctly, unprompted. */
    case Solid = 'solid';

    /** Got there, but with help or hesitation. */
    case Shaky = 'shaky';

    /** Taught, but never landed. */
    case Missed = 'missed';
}
