<?php

namespace App\Courses\Exceptions;

/**
 * Free text that cannot become a folder name in the memory tree (spec 3.2).
 */
final class UnusableSubtopicName extends CourseException
{
    public static function for(string $text, string $because): self
    {
        return new self("Subtopic name [{$text}] cannot be used: {$because}");
    }
}
