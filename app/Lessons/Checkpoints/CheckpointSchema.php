<?php

namespace App\Lessons\Checkpoints;

use App\Assessment\QuestionSchema;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The `json/checkpoint.v1` contract (spec 5.2.2).
 *
 * `{checkpoint: {section_index, questions[1-3]{...}}}` -- wrapped, unlike
 * `json/syllabus.v1`, because 5.2.2 writes it that way and the wrapper is what
 * lets the same envelope carry a future sibling without renaming anything.
 *
 * One to three questions. A section is a few minutes of teaching; a fourth
 * question turns a checkpoint into a quiz and the student stops reading them.
 *
 * `concept_tag` is a SHOULD, and the schema says so by making it nullable.
 * Checkpoint::fromModel drops a tag the syllabus does not declare rather than
 * rejecting the checkpoint: 5.2.2 calls the tag droppable without breaking the
 * loop, and a lost tag costs a line in the summary while a rejected checkpoint
 * costs the student their section.
 */
final class CheckpointSchema
{
    public const CONTRACT = 'json/checkpoint.v1';

    public const MIN_QUESTIONS = 1;

    public const MAX_QUESTIONS = 3;

    /**
     * @return Closure(JsonSchema): array<string, Type>
     */
    public static function definition(): Closure
    {
        return fn (JsonSchema $schema): array => [
            'checkpoint' => $schema->object([
                'section_index' => $schema->integer()->required()
                    ->description('The 1-based section this checkpoint closes.'),

                'questions' => $schema->array()
                    ->min(self::MIN_QUESTIONS)
                    ->max(self::MAX_QUESTIONS)
                    ->required()
                    ->items(QuestionSchema::definition($schema)),
            ])->withoutAdditionalProperties()->required(),
        ];
    }
}
