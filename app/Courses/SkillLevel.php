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
     * What the level is called where a student picks one (spec 8.7.1).
     *
     * Here rather than on the screen so the picker, a course card and a
     * rollup cannot each invent their own word for 201.
     */
    public function title(): string
    {
        return match ($this) {
            self::Introductory => 'Introductory',
            self::Intermediate => 'Intermediate',
            self::Advanced => 'Advanced',
        };
    }

    /**
     * The `{level}` segment of a memory path (spec 4.5.3).
     */
    public function pathSegment(): string
    {
        return (string) $this->value;
    }
}
