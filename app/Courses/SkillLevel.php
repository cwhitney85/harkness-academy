<?php

namespace App\Courses;

/**
 * How deep a course goes (spec 4.5.6).
 *
 * Pedagogy, never price: 101 may be *longer* than 301 because beginners need
 * more scaffolding. Anything that ties this to a plan or a tier is a bug.
 */
enum SkillLevel: int
{
    case Introductory = 101;
    case Intermediate = 201;
    case Advanced = 301;

    /**
     * The `{level}` segment of a memory path (spec 4.5.3).
     */
    public function pathSegment(): string
    {
        return (string) $this->value;
    }
}
