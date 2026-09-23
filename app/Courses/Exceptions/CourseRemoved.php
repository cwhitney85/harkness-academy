<?php

namespace App\Courses\Exceptions;

/**
 * An admin removed the course while the model was answering for it (spec
 * 16.2.8, ledger D70). Nothing the answer would have written is saved.
 */
final class CourseRemoved extends CourseException
{
    private function __construct(string $message, public readonly int $courseId)
    {
        parent::__construct($message);
    }

    public static function during(int $courseId): self
    {
        return new self("Course [{$courseId}] was removed while the model was answering for it; nothing is saved.", $courseId);
    }
}
