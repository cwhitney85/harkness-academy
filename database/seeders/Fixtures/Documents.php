<?php

namespace Database\Seeders\Fixtures;

/**
 * The two documents a fixture lesson leaves behind, written to their schemas.
 *
 * Shared between the fixture courses so that a change to `json/quiz.v1` or
 * `json/lesson-summary.v1` lands in one place. The arrays are still written
 * out in full at each call site -- what is factored out here is the envelope,
 * not the content, because the content is the whole point (8.2).
 */
final class Documents
{
    /**
     * A `json/lesson-summary.v1` document (spec 5.4.2).
     *
     * Every slug must be one the lesson's own sections declare; that is the
     * rule LessonSummary::fromModel enforces on real output, and a fixture
     * that broke it would render a SUMMARY.md no engine could have written.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $concepts  slug, mastery, evidence
     * @param  list<string>  $struggles
     * @param  list<string>  $questions
     * @return array<string, mixed>
     */
    public static function summary(string $prose, array $concepts, array $struggles = [], array $questions = []): array
    {
        return [
            'summary' => [
                'prose' => $prose,
                'concepts' => array_map(
                    static fn (array $concept): array => [
                        'slug' => $concept[0],
                        'mastery' => $concept[1],
                        'evidence' => $concept[2],
                    ],
                    $concepts,
                ),
                'struggles' => $struggles,
                'questions_asked' => $questions,
            ],
        ];
    }

    /**
     * A `json/quiz.v1` document (spec 6.1).
     *
     * @param  list<array<string, mixed>>  $questions
     * @param  list<array{title: string, why: string}>  $reading
     * @return array<string, mixed>
     */
    public static function quiz(string $scope, array $questions, array $reading = []): array
    {
        return [
            'quiz' => [
                'scope' => $scope,
                'questions' => $questions,
                'suggested_reading' => $reading,
            ],
        ];
    }
}
