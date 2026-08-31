<?php

namespace App\Assessment;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The `json/quiz.v1` contract (spec 6.1, inventory 2.3.2).
 *
 * The checkpoint schema generalized: a scope label instead of a section index,
 * the suggested reading a homework quiz carries alongside its questions, and a
 * question count that comes from the caller's purpose rather than being fixed
 * at one to three (QuizLength).
 *
 * **Landed by package 5, owned by package 6** (ledger D12). 5.4.1's completion
 * chain calls `homework.generate`, which declares this output, so the lesson
 * engine cannot run without it. `exam.final` and `quiz.refresher` declare it
 * too and package 6 wires them up.
 */
final class QuizSchema
{
    public const CONTRACT = 'json/quiz.v1';

    /**
     * Three, per the homework prompt: real, findable material or nothing.
     */
    public const MAX_READING = 3;

    /**
     * The contract, cut to the length its purpose asks for.
     *
     * Pre: `$length` names the purpose this call serves -- the shape is the
     *      same for all three, the count is not (QuizLength).
     *
     * @return Closure(JsonSchema): array<string, Type>
     */
    public static function definition(QuizLength $length): Closure
    {
        return fn (JsonSchema $schema): array => [
            'quiz' => $schema->object([
                'scope' => $schema->string()->min(1)->required()
                    ->description('What this quiz covers, in the student\'s words.'),

                'questions' => $schema->array()
                    ->min($length->min)
                    ->max($length->max)
                    ->required()
                    ->items(QuestionSchema::definition($schema)),

                // Required but allowed to be empty: the homework prompt asks
                // for nothing rather than filler, and an exam has no reading
                // at all. A required-and-empty array is one shape for every
                // consumer; an absent key is two.
                'suggested_reading' => $schema->array()->max(self::MAX_READING)->required()
                    ->items($schema->object([
                        'title' => $schema->string()->min(1)->required(),
                        'why' => $schema->string()->min(1)->required()
                            ->description('One line on why this student in particular should read it.'),
                    ])->withoutAdditionalProperties()),
            ])->withoutAdditionalProperties()->required(),
        ];
    }
}
