<?php

namespace App\Courses;

/**
 * One line of a module rollup (spec 3.3): where a subtopic's knowledge lives,
 * and how far the student has formally got in it.
 */
final readonly class SubtopicPointer
{
    /**
     * @param  list<SkillLevel>  $levelsCompleted  ascending; empty is normal --
     *                                             a student can know a great deal about a subtopic
     *                                             without having finished a course in it.
     */
    public function __construct(
        public string $path,
        public string $subtopicSlug,
        public string $title,
        public array $levelsCompleted,
    ) {}
}
