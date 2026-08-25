<?php

namespace App\Lessons\Exceptions;

/**
 * A section index that does not name a section of this lesson.
 *
 * A caller bug everywhere inside the engine; at the transport it is a request
 * for a URL that does not exist, and the controller turns it into a 404 rather
 * than letting it reach the model (spec 5.2.6).
 */
final class NoSuchSection extends LessonException
{
    public static function at(int $index, int $total): self
    {
        return new self("Section [{$index}] is outside this lesson's 1..{$total}.");
    }
}
