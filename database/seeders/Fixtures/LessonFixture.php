<?php

namespace Database\Seeders\Fixtures;

/**
 * How far one lesson of a fixture course got, and what it left behind.
 *
 * The list of sections *is* the progress: two sections on a four-section
 * lesson is a lesson mid-flight at section 2, and the seeder needs no separate
 * flag to say so. `completed` is the one thing the list cannot express, since
 * a lesson can have every section taught and still not have been handed in.
 */
final readonly class LessonFixture
{
    /**
     * @param  list<SectionFixture>  $taught  one per section reached, in order
     * @param  list<CheckpointFixture>  $checkpoints
     * @param  array<string, mixed>|null  $summary  a `json/lesson-summary.v1` document
     * @param  array<string, mixed>|null  $homework  a `json/quiz.v1` document
     * @param  array<string, string>  $homeworkAnswers  question id => option id; empty is ungraded (8.2.5)
     */
    public function __construct(
        public int $position,
        public array $taught,
        public bool $completed = false,
        public array $checkpoints = [],
        public ?array $summary = null,
        public ?array $homework = null,
        public array $homeworkAnswers = [],
    ) {}
}
