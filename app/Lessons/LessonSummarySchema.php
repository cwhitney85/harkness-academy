<?php

namespace App\Lessons;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The `json/lesson-summary.v1` contract (spec 5.4.2).
 *
 * `{summary: {prose, concepts[], struggles[], questions_asked[]}}`.
 *
 * The one rule the schema cannot state is the important one: a concept slug
 * must be declared by *this lesson's* sections. JSON Schema can constrain the
 * slug's shape but not its membership of a set that differs per lesson, so
 * LessonSummary::fromModel enforces it and 5.4.2's done-condition -- an
 * undeclared slug fails validation -- is tested against that.
 *
 * This document is what a refresher quiz is eventually built from (6.4), which
 * is why the prompt is so insistent about not overstating: an inflated summary
 * becomes a quiz the student cannot answer, weeks later, with nothing left to
 * explain why.
 */
final class LessonSummarySchema
{
    public const CONTRACT = 'json/lesson-summary.v1';

    /**
     * @return Closure(JsonSchema): array<string, Type>
     */
    public static function definition(): Closure
    {
        return fn (JsonSchema $schema): array => [
            'summary' => $schema->object([
                'prose' => $schema->string()->min(1)->required()
                    ->description('What happened in this lesson, for a reader who was not there.'),

                'concepts' => $schema->array()->min(1)->required()->items(
                    $schema->object([
                        'slug' => $schema->string()->required()
                            ->description('A concept slug declared by one of this lesson\'s sections.'),

                        'mastery' => $schema->string()->enum(['solid', 'shaky', 'missed'])->required(),

                        'evidence' => $schema->string()->min(1)->required()
                            ->description('What in the lesson supports this verdict.'),
                    ])->withoutAdditionalProperties(),
                ),

                'struggles' => $schema->array()->required()->items($schema->string()->min(1))
                    ->description('What the student found hard, specifically enough for the next lesson to act on.'),

                'questions_asked' => $schema->array()->required()->items($schema->string()->min(1))
                    ->description('What the student asked of their own accord.'),
            ])->withoutAdditionalProperties()->required(),
        ];
    }
}
