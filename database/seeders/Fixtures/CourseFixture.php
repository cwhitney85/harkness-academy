<?php

namespace Database\Seeders\Fixtures;

use App\Courses\SkillLevel;

/**
 * One course of the design fixture, content and all (spec 8.2).
 *
 * Everything a course needs to exist as if a student had walked it: the two
 * documents the interviews would have written, the syllabus they approved, and
 * as much lesson and exam material as they have reached. No inference produced
 * any of it (8.2.9) -- it is written to the same schemas the engines write to,
 * which is what makes it a stand-in rather than a guess.
 *
 * The subtopic is free text, so `slug` is the folder its documents live in and
 * `title` is what the student called it (spec 4.1).
 */
final readonly class CourseFixture
{
    /**
     * @param  array<string, mixed>  $syllabus  a `json/syllabus.v1` document
     * @param  list<LessonFixture>  $lessons  the lessons reached, in teaching order
     * @param  array<string, mixed>|null  $exam  a `json/quiz.v1` final exam
     * @param  list<array<string, string>>  $sittings  one map of question id => option id per sitting, oldest first
     */
    public function __construct(
        public string $moduleSlug,
        public string $slug,
        public string $title,
        public SkillLevel $level,
        public string $workingKnowledge,
        public string $criteria,
        public array $syllabus,
        public array $lessons = [],
        public ?array $exam = null,
        public array $sittings = [],
    ) {}
}
