<?php

namespace App\Assessment;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The multiple-choice question every quiz is made of (spec 5.2.2, 6.1).
 *
 * Shared by `json/checkpoint.v1` and `json/quiz.v1` because 2.3.2 defines the
 * second as the first generalized. One definition, so a change to what a
 * question is cannot land in a checkpoint and miss the final exam.
 *
 * `correct_option_id` and `explanation` are part of the model's output on
 * purpose: grading is code, not a second model call (ledger D5). They are also
 * the two fields 5.2.3 strips before any of this reaches a client.
 */
final class QuestionSchema
{
    /**
     * Four options, exactly (spec 6.6.1). Fewer is a coin flip and leaves the
     * distribution of 6.6 a D position short; more is a reading test.
     */
    public const MIN_OPTIONS = 4;

    public const MAX_OPTIONS = 4;

    /**
     * Ids the model invents, so they are constrained rather than trusted:
     * they end up in `quiz_attempts` columns and in URLs.
     */
    public const ID_PATTERN = '^[A-Za-z0-9_-]{1,64}$';

    public static function definition(JsonSchema $schema): Type
    {
        return $schema->object([
            'id' => $schema->string()->pattern(self::ID_PATTERN)->required()
                ->description('Unique within this quiz.'),

            'prompt' => $schema->string()->min(1)->required(),

            'options' => $schema->array()
                ->min(self::MIN_OPTIONS)
                ->max(self::MAX_OPTIONS)
                ->required()
                ->items($schema->object([
                    'id' => $schema->string()->pattern(self::ID_PATTERN)->required(),
                    'text' => $schema->string()->min(1)->required(),
                ])->withoutAdditionalProperties()),

            'correct_option_id' => $schema->string()->pattern(self::ID_PATTERN)->required()
                ->description('Must be the id of one of this question\'s options.'),

            'explanation' => $schema->string()->min(1)->required()
                ->description('Shown only after the student answers.'),

            'concept_tag' => $schema->string()->nullable()->required()
                ->description('A concept slug from the syllabus, or null.'),
        ])->withoutAdditionalProperties();
    }
}
