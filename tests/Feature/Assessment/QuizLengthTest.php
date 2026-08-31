<?php

namespace Tests\Feature\Assessment;

use App\Ai\Prompts\PromptRepository;
use App\Assessment\QuizLength;
use App\Assessment\QuizSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * How long a quiz is, and why the exam's length is load-bearing.
 *
 * `assessment.final_exam.pass_mark` is a fraction, so what it demands in whole
 * questions depends on how many there are. Nothing fixed that number until
 * QuizLength: an unbounded exam meant one configured pass mark could mean
 * anything from 70% to "get all three right".
 */
class QuizLengthTest extends AssessmentTestCase
{
    #[Test]
    public function each_purpose_asks_the_schema_for_its_own_length(): void
    {
        foreach ([
            [QuizLength::exam(), 8, 12],
            [QuizLength::homework(), 3, 6],
            [QuizLength::refresher(), 3, 5],
        ] as [$length, $min, $max]) {
            $this->assertSame($min, $length->min);
            $this->assertSame($max, $length->max);

            $questions = QuizSchema::definition($length)(new JsonSchemaTypeFactory)['quiz']
                ->toArray()['properties']['questions'];

            $this->assertSame($min, $questions['minItems']);
            $this->assertSame($max, $questions['maxItems']);
        }
    }

    #[Test]
    public function the_pass_mark_means_roughly_one_thing_across_the_exams_range(): void
    {
        $length = QuizLength::exam();
        $passMark = (float) config('assessment.final_exam.pass_mark');

        $effective = [];

        for ($questions = $length->min; $questions <= $length->max; $questions++) {
            $effective[$questions] = $length->toPass($questions, $passMark) / $questions;
        }

        // 70% at ten questions, and never more than eight points above it.
        // The spread is inherent to counting whole questions; the 70-to-100%
        // an unbounded exam allowed was not.
        $this->assertSame(0.7, round($effective[10], 4));
        $this->assertGreaterThanOrEqual($passMark, min($effective));
        $this->assertLessThanOrEqual(0.78, max($effective));
    }

    #[Test]
    public function every_prompt_asks_for_the_length_its_schema_will_accept(): void
    {
        $prompts = new PromptRepository(base_path('prompts'));

        foreach ([
            'exam.final' => QuizLength::exam(),
            'homework.generate' => QuizLength::homework(),
            'quiz.refresher' => QuizLength::refresher(),
        ] as $key => $length) {
            // Prompt files are static text and cannot read a constant, so the
            // numbers live in two places. A prompt asking for a length its own
            // schema rejects would burn a retry on every single call.
            $this->assertStringContainsString(
                $length->describe(),
                $prompts->get($key)->body,
                "Prompt [{$key}] does not ask for the length its schema enforces.",
            );
        }
    }
}
