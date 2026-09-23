<?php

namespace Tests\Feature\Lessons;

use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Lessons\Streaming\EventStream;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Lesson;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fixture every package 5 test starts from: a real approved course.
 *
 * Built by running package 4's approval rather than by inserting rows, so the
 * lesson rows, their GUIDANCE documents and the syllabus they came from are
 * consistent with each other by construction. A hand-built fixture would drift
 * from what 4.5.5 actually produces, and package 5's whole job is reading what
 * package 4 wrote.
 */
abstract class LessonEngineTestCase extends TestCase
{
    use RefreshDatabase;

    protected const WK = "## Solid\n\n- Reads graphs confidently.\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- Letters standing for numbers: variable, expression.";

    protected const CRITERIA = "## Goals\n\n- Help my daughter with her homework.\n\n## Success\n\n- Explain one-step equations out loud.";

    /**
     * Prompts sent to the model, in order, for asserting on what was asked.
     *
     * @var list<string>
     */
    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    /**
     * An `active` course whose syllabus is approved and whose lesson 1 is open.
     */
    protected function activeCourse(): Course
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->create();

        // Seeded before the syllabus row, so the factory's version pin reads
        // these rather than writing placeholders of its own -- which keeps the
        // version count honest for the erosion tests.
        $tree = app(MemoryTree::class);
        $tree->put($course, $course->workingKnowledgePath(), self::WK);
        $tree->put($course, CourseCriteria::pathFor($course), self::CRITERIA);

        app(ApproveSyllabus::class)($course, CourseSyllabus::factory()->for($course)->create());

        return $course->refresh();
    }

    protected function lesson(?Course $course = null, int $position = 1): Lesson
    {
        $course ??= $this->activeCourse();

        return $course->lessons()->where('position', $position)->firstOrFail();
    }

    /**
     * Script the model turn by turn.
     *
     * Each entry is what the next call returns: a string for a streamed or
     * markdown reply, an array for a structured one. A call beyond the end of
     * the list fails the test rather than inventing a reply.
     *
     * @param  list<array<string, mixed>|string>  $responses
     */
    protected function scriptModel(array $responses): void
    {
        $this->sent = [];
        $remaining = $responses;

        $this->fakeModel(function (string $prompt) use (&$remaining): array|string {
            $this->sent[] = $prompt;

            if ($remaining === []) {
                $this->fail('The model was called more times than the test scripted.');
            }

            return array_shift($remaining);
        });
    }

    /**
     * An EventStream that collects its frames instead of echoing them.
     *
     * @param  list<string>  $frames
     * @param  (Closure(): float)|null  $clock
     */
    protected function collectingStream(array &$frames, ?Closure $clock = null): EventStream
    {
        return new EventStream(function (string $frame) use (&$frames): void {
            $frames[] = $frame;
        }, $clock);
    }

    /**
     * The events a collected stream carries, decoded.
     *
     * @param  list<string>  $frames
     * @return list<array{name: string, data: array<string, mixed>}>
     */
    protected function eventsIn(array $frames): array
    {
        return EventStream::parse(implode('', $frames));
    }

    /**
     * Just the event names, in order -- what most assertions are about.
     *
     * @param  list<string>  $frames
     * @return list<string>
     */
    protected function eventNamesIn(array $frames): array
    {
        return array_map(
            static fn (array $event): string => $event['name'],
            $this->eventsIn($frames),
        );
    }

    /**
     * Event names with runs of deltas collapsed to one.
     *
     * A turn arrives as however many deltas the provider felt like sending,
     * which is not a fact about the protocol -- what 5.2.5 pins down is the
     * *shape* of a turn: it starts, it says things, it ends.
     *
     * @param  list<string>  $frames
     * @return list<string>
     */
    protected function eventShapeIn(array $frames): array
    {
        $shape = [];

        foreach ($this->eventNamesIn($frames) as $name) {
            if ($shape === [] || end($shape) !== $name) {
                $shape[] = $name;
            }
        }

        return $shape;
    }

    /**
     * The one event of a given name in a collected stream.
     *
     * @param  list<string>  $frames
     * @return array<string, mixed>
     */
    protected function eventNamed(array $frames, string $name): array
    {
        foreach ($this->eventsIn($frames) as $event) {
            if ($event['name'] === $name) {
                return $event['data'];
            }
        }

        $this->fail("No [{$name}] event in the stream.");
    }

    /**
     * The whole text of a turn, reassembled from its deltas.
     *
     * @param  list<string>  $frames
     */
    protected function turnTextIn(array $frames): string
    {
        $text = '';

        foreach ($this->eventsIn($frames) as $event) {
            if ($event['name'] === 'turn.delta') {
                $text .= $event['data']['text'];
            }
        }

        return $text;
    }

    /**
     * A `json/checkpoint.v1` document the schema and Checkpoint both accept.
     *
     * @return array<string, mixed>
     */
    protected function checkpointResponse(int $sectionIndex = 1, string $conceptTag = 'variable'): array
    {
        return [
            'checkpoint' => [
                'section_index' => $sectionIndex,
                'questions' => [
                    [
                        'id' => 'q1',
                        'prompt' => 'In 3n + 2, what is n?',
                        'options' => [
                            ['id' => 'a', 'text' => 'A quantity you do not know yet'],
                            ['id' => 'b', 'text' => 'The number three'],
                            ['id' => 'c', 'text' => 'An instruction to add'],
                            ['id' => 'd', 'text' => 'The answer to the question'],
                        ],
                        'correct_option_id' => 'a',
                        'explanation' => 'A letter stands in for a quantity that has not been pinned down.',
                        'concept_tag' => $conceptTag,
                    ],
                ],
            ],
        ];
    }

    /**
     * A `json/lesson-summary.v1` document keyed on lesson 1's own concepts.
     *
     * @param  list<string>  $slugs
     * @return array<string, mixed>
     */
    protected function summaryResponse(array $slugs = ['variable', 'expression', 'coefficient']): array
    {
        return [
            'summary' => [
                'prose' => 'We started from a shopping list and let a letter stand for the unknown count.',
                'concepts' => array_map(
                    static fn (string $slug): array => [
                        'slug' => $slug,
                        'mastery' => 'solid',
                        'evidence' => "Used {$slug} correctly without prompting.",
                    ],
                    $slugs,
                ),
                'struggles' => ['Still writes 3n as n3 about half the time.'],
                'questions_asked' => ['Can a letter stand for more than one number?'],
            ],
        ];
    }

    /**
     * A `json/quiz.v1` document, for the homework step.
     *
     * @return array<string, mixed>
     */
    protected function quizResponse(): array
    {
        return [
            'quiz' => [
                'scope' => 'Letters as numbers',
                'questions' => [
                    [
                        'id' => 'h1',
                        'prompt' => 'Write "five more than a number" as an expression.',
                        'options' => [
                            ['id' => 'a', 'text' => 'n + 5'],
                            ['id' => 'b', 'text' => '5n'],
                            ['id' => 'c', 'text' => 'n - 5'],
                            ['id' => 'd', 'text' => '5 - n'],
                        ],
                        'correct_option_id' => 'a',
                        'explanation' => '"More than" is addition.',
                        'concept_tag' => 'expression',
                    ],
                ],
                'suggested_reading' => [
                    ['title' => 'Khan Academy: intro to variables', 'why' => 'Short, and uses the shopping-list framing you liked.'],
                ],
            ],
        ];
    }
}
