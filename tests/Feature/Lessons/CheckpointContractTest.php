<?php

namespace Tests\Feature\Lessons;

use App\Ai\Prompts\PromptRepository;
use App\Ai\Tier;
use App\Assessment\QuestionSchema;
use App\Assessment\QuizLength;
use App\Assessment\QuizSchema;
use App\Lessons\Checkpoints\CheckpointSchema;
use App\Lessons\LessonSummarySchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 5.2.2, 5.4.2 -- the prompts and the schemas name the same contracts.
 *
 * The gateway checks only that a prompt declaring a json output was handed *a*
 * schema (2.3.1); nothing makes it the right one. A frontmatter typo would let
 * `checkpoint.generate` validate happily against the summary schema, so the
 * agreement is pinned here, following SyllabusContractTest's precedent.
 */
class CheckpointContractTest extends TestCase
{
    #[Test]
    public function each_prompt_declares_the_schema_its_package_owns(): void
    {
        $contracts = [
            'checkpoint.generate' => CheckpointSchema::CONTRACT,
            'lesson.summarize' => LessonSummarySchema::CONTRACT,
            'homework.generate' => QuizSchema::CONTRACT,
        ];

        foreach ($contracts as $key => $contract) {
            $this->assertSame($contract, $this->prompts()->get($key)->outputContract(), $key);
        }
    }

    #[Test]
    public function checkpoints_are_generated_at_the_cheap_tier(): void
    {
        // Spec 5.2.1 names the tier, and it is the reason a checkpoint after
        // every section is affordable at all.
        $this->assertSame(Tier::Cheap, $this->prompts()->get('checkpoint.generate')->tier);
    }

    #[Test]
    public function the_checkpoint_document_is_wrapped_and_bounded(): void
    {
        $fields = CheckpointSchema::definition()(new JsonSchemaTypeFactory);

        // 5.2.2 writes it wrapped: `{checkpoint: {...}}`.
        $this->assertSame(['checkpoint'], array_keys($fields));

        $this->assertSame(1, CheckpointSchema::MIN_QUESTIONS);
        $this->assertSame(3, CheckpointSchema::MAX_QUESTIONS);
        // Four, exactly: 6.6's distribution has no D to choose otherwise.
        $this->assertSame(4, QuestionSchema::MIN_OPTIONS);
        $this->assertSame(4, QuestionSchema::MAX_OPTIONS);
    }

    #[Test]
    public function the_summary_document_is_wrapped_too(): void
    {
        $fields = LessonSummarySchema::definition()(new JsonSchemaTypeFactory);

        $this->assertSame(['summary'], array_keys($fields));
    }

    #[Test]
    public function the_quiz_schema_is_the_checkpoint_schema_generalized(): void
    {
        // Inventory 2.3.2: "one schema, three consumers". Both contracts build
        // their questions from QuestionSchema, so a change to what a question
        // is cannot land in one and miss the other.
        $this->assertSame(['quiz'], array_keys(QuizSchema::definition(QuizLength::homework())(new JsonSchemaTypeFactory)));

        foreach (['exam.final', 'quiz.refresher', 'homework.generate'] as $key) {
            $this->assertSame(QuizSchema::CONTRACT, $this->prompts()->get($key)->outputContract(), $key);
        }
    }

    private function prompts(): PromptRepository
    {
        return app(PromptRepository::class);
    }
}
