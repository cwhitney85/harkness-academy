<?php

namespace App\Courses\Syllabus;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * The `json/syllabus.v1` contract (spec 4.5.2).
 *
 * The gateway checks only that a prompt declaring a json output was handed a
 * schema; the schema itself belongs to the package that owns the contract, so
 * it lives here rather than in package 2.
 *
 * The `concepts` slugs are the connective tissue of the whole system: declared
 * here, tagged in checkpoints (5.2.2), reported in summaries (5.4.2), drawn on
 * by exams and refreshers (6.3/6.4). SyllabusConcepts is what the rest of the
 * system resolves them through.
 *
 * **`final_exam_concepts` is emphasis, and its description is the only place
 * the model is told so** -- `prompts/syllabus/generate.md` never names the
 * field. It read "slugs the final exam draws on", which invites a list
 * written as a scope: the first real course declared 44 of them, trying to
 * name everything the exam might ask, against an exam of 12 questions. Since
 * 6.3.3 the exam is written across every lesson whatever this list says, so
 * the description warns that a longer one buys no coverage (8.7.7, and the
 * same correction the syllabus screen's panel took).
 */
final class SyllabusSchema
{
    /**
     * The `output:` frontmatter value the prompt files must declare.
     */
    public const CONTRACT = 'json/syllabus.v1';

    /**
     * Lowercase, hyphenated, stable -- the shape the prompts ask for.
     */
    public const SLUG_PATTERN = '^[a-z0-9]+(-[a-z0-9]+)*$';

    /**
     * The definition handed to the gateway's `schema()`.
     *
     * @return Closure(JsonSchema): array<string, Type>
     */
    public static function definition(): Closure
    {
        return fn (JsonSchema $schema): array => [
            'course_title' => $schema->string()->min(1)->required(),

            'overview' => $schema->string()->min(1)->required()
                ->description('One or two paragraphs the student reads before approving.'),

            'lessons' => $schema->array()->min(1)->required()->items(
                $schema->object([
                    'position' => $schema->integer()->required()
                        ->description('1-based teaching order.'),

                    'title' => $schema->string()->min(1)->required(),

                    'objective' => $schema->string()->min(1)->required()
                        ->description('What the student can do afterwards that they could not before.'),

                    'sections' => $schema->array()->min(1)->required()->items(
                        $schema->object([
                            'title' => $schema->string()->min(1)->required(),

                            'concepts' => $schema->array()->min(1)->required()->unique()->items(
                                $schema->string()->pattern(self::SLUG_PATTERN),
                            )->description('Concept slugs introduced or exercised in this section.'),
                        ])->withoutAdditionalProperties(),
                    ),

                    'homework_focus' => $schema->string()->min(1)->required()
                        ->description('What the quiz homework for this lesson should press on.'),

                    'estimated_minutes' => $schema->integer()->required(),
                ])->withoutAdditionalProperties(),
            ),

            'final_exam_concepts' => $schema->array()->min(1)->required()->unique()->items(
                $schema->string()->pattern(self::SLUG_PATTERN),
            )->description('Where the final exam should put its weight -- not its scope. The exam is written across every lesson of the syllabus (6.3.1), so a longer list here buys no coverage. Every slug must be declared by a lesson section.'),

            'subtopic_mismatch' => $schema->string()->nullable()->required()
                ->description('Null unless the goals do not fit the chosen subtopic; then one sentence saying how. A flag, never a refusal (spec 4.5.7).'),
        ];
    }
}
