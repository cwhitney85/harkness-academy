<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\Exceptions\CourseNotOpen;
use App\Courses\Exceptions\MissingCourseDocument;
use App\Courses\Exceptions\SyllabusNotDraftable;
use App\Courses\Exceptions\SyllabusUnusable;
use App\Courses\Syllabus\SyllabusGenerator;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\UsageEvent;
use Database\Factories\CourseSyllabusFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.1 / 4.5.4 -- generation, the one retry, and the revision loop.
 *
 * The rule under test throughout: nothing is written unless the model produced
 * a usable syllabus, so a failure costs the student nothing (ledger D10.4).
 */
class SyllabusGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const WK = "## Solid\n\n- Reads graphs confidently.\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- Letters standing for numbers.";

    private const CRITERIA = "## Goals\n\n- Help my daughter with her homework.\n\n## Success\n\n- Explain one-step equations out loud.";

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function a_first_generation_records_the_syllabus_and_what_it_was_built_from(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus()]);
        $course = $this->course();

        $syllabus = $this->generator()->first($course);

        $this->assertSame(1, $syllabus->generation);
        $this->assertNull($syllabus->feedback);
        $this->assertSame('Algebra: Getting Comfortable with the Unknown', $syllabus->syllabus_json['course_title']);

        // The pin is the point: a replay months later must see the WK the
        // model actually read, not today's (ledger D10.1).
        $tree = app(MemoryTree::class);
        $this->assertSame(
            $tree->document($course->student, $course->workingKnowledgePath())->versionId,
            $syllabus->wk_version_id,
        );

        // Generation performs no transition: the goals interview already put
        // the course here (ledger D10.4).
        $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()->status);
    }

    #[Test]
    public function prose_the_model_escaped_one_level_too_many_is_stored_as_prose(): void
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();
        $syllabus['overview'] = 'Two paragraphs.\n\nAnd the second.';
        $syllabus['lessons'][0]['objective'] = 'Place the period on a timeline (c. 476\u20131500).';
        $syllabus['lessons'][0]['title'] = 'Escaping with \d in regular expressions';

        $this->scriptModel([$syllabus]);

        $stored = $this->generator()->first($this->course())->syllabus_json;

        $this->assertSame("Two paragraphs.\n\nAnd the second.", $stored['overview']);
        $this->assertSame('Place the period on a timeline (c. 476–1500).', $stored['lessons'][0]['objective']);

        // Conservative by design: a string that is not a whole JSON string
        // body is left exactly as the model wrote it (spec 4.8).
        $this->assertSame('Escaping with \d in regular expressions', $stored['lessons'][0]['title']);
    }

    #[Test]
    public function the_prompt_carries_the_documents_and_the_depth(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus()]);

        $this->generator()->first($this->course());

        $prompt = $this->sent[0];

        $this->assertStringContainsString('Reads graphs confidently', $prompt);
        $this->assertStringContainsString('help my daughter', strtolower($prompt));
        $this->assertStringContainsString('- Lessons: 8-10', $prompt);
    }

    #[Test]
    public function every_call_lands_in_usage_events(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus()]);

        $this->generator()->first($this->course());

        $this->assertSame(1, UsageEvent::query()->where('feature', 'course.syllabus')->count());
    }

    #[Test]
    public function one_bad_response_is_retried(): void
    {
        $this->scriptModel(['not a syllabus at all', CourseSyllabusFactory::exampleSyllabus()]);

        $syllabus = $this->generator()->first($this->course());

        $this->assertSame(1, $syllabus->generation);
        $this->assertCount(2, $this->sent);
    }

    #[Test]
    public function two_bad_responses_fail_closed(): void
    {
        $this->scriptModel(['nope', 'still nope']);
        $course = $this->course();

        try {
            $this->generator()->first($course);

            $this->fail('Expected the syllabus to fail closed.');
        } catch (SyllabusUnusable) {
            // Nothing downstream exists to protect, so the student gets an
            // error rather than half a course (spec 4.5.1).
            $this->assertSame(0, CourseSyllabus::query()->count());
            $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()->status);
        }
    }

    #[Test]
    public function a_syllabus_whose_exam_invents_a_concept_is_unusable(): void
    {
        $broken = CourseSyllabusFactory::exampleSyllabus();
        $broken['final_exam_concepts'] = ['variable', 'quadratic-formula'];

        $this->scriptModel([$broken, $broken]);

        $this->expectException(SyllabusUnusable::class);

        $this->generator()->first($this->course());
    }

    #[Test]
    public function a_syllabus_with_a_gap_in_its_lesson_positions_is_unusable(): void
    {
        $broken = CourseSyllabusFactory::exampleSyllabus();
        $broken['lessons'][1]['position'] = 7;

        $this->scriptModel([$broken, $broken]);

        $this->expectException(SyllabusUnusable::class);

        $this->generator()->first($this->course());
    }

    #[Test]
    public function a_second_first_generation_is_refused(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus(), CourseSyllabusFactory::exampleSyllabus()]);
        $course = $this->course();

        $this->generator()->first($course);

        $this->expectException(SyllabusNotDraftable::class);

        $this->generator()->first($course->fresh());
    }

    #[Test]
    public function generation_needs_both_interview_documents(): void
    {
        $course = $this->course(withCriteria: false);

        $this->expectException(MissingCourseDocument::class);

        $this->generator()->first($course);
    }

    #[Test]
    public function a_revision_carries_the_feedback_and_the_previous_syllabus(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus(), CourseSyllabusFactory::exampleSyllabus()]);
        $course = $this->course();

        $this->generator()->first($course);
        $revised = $this->generator()->revision($course->fresh(), 'Too much theory in lesson one.');

        $this->assertSame(2, $revised->generation);
        $this->assertSame('Too much theory in lesson one.', $revised->feedback);

        $this->assertStringContainsString('Too much theory in lesson one.', $this->sent[1]);
        $this->assertStringContainsString('Letters as Numbers', $this->sent[1]);

        // Back to a draft awaiting approval.
        $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()->status);
    }

    #[Test]
    public function the_fourth_revision_is_refused(): void
    {
        $this->scriptModel(array_fill(0, 5, CourseSyllabusFactory::exampleSyllabus()));
        $course = $this->course();

        $this->generator()->first($course);

        foreach (['one', 'two', 'three'] as $round) {
            $this->generator()->revision($course->fresh(), "Change {$round}.");
        }

        $this->assertSame(4, $course->syllabi()->count());

        try {
            $this->generator()->revision($course->fresh(), 'Change four.');

            $this->fail('Expected the cap to hold.');
        } catch (SyllabusNotDraftable $e) {
            $this->assertStringContainsString('all 3 revisions are used', $e->getMessage());
            $this->assertSame(4, $course->syllabi()->count());
        }
    }

    #[Test]
    public function a_failed_revision_keeps_the_draft_the_student_already_has(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus(), 'nope', 'still nope']);
        $course = $this->course();

        $first = $this->generator()->first($course);

        try {
            $this->generator()->revision($course->fresh(), 'Make it shorter.');

            $this->fail('Expected the revision to fail closed.');
        } catch (SyllabusUnusable) {
            $course->refresh();

            $this->assertSame(1, $course->syllabi()->count());
            $this->assertSame($first->id, $course->latestSyllabus()->first()->id);

            // Left mid-revision on purpose: the student can try again or
            // approve what they have (ledger D10.4).
            $this->assertSame(CourseStatus::SyllabusRevision, $course->status);
        }
    }

    #[Test]
    public function a_revision_can_be_retried_after_a_failure(): void
    {
        $this->scriptModel([
            CourseSyllabusFactory::exampleSyllabus(),
            'nope',
            'still nope',
            CourseSyllabusFactory::exampleSyllabus(),
        ]);
        $course = $this->course();

        $this->generator()->first($course);

        try {
            $this->generator()->revision($course->fresh(), 'Make it shorter.');
        } catch (SyllabusUnusable) {
            // Expected; the retry below is the point.
        }

        $revised = $this->generator()->revision($course->fresh(), 'Make it shorter.');

        $this->assertSame(2, $revised->generation);
        $this->assertSame(CourseStatus::SyllabusDraft, $course->fresh()->status);
    }

    #[Test]
    public function a_revision_needs_something_to_react_to(): void
    {
        $this->scriptModel([CourseSyllabusFactory::exampleSyllabus()]);
        $course = $this->course();

        $this->generator()->first($course);

        $this->expectException(SyllabusNotDraftable::class);

        $this->generator()->revision($course->fresh(), '   ');
    }

    #[Test]
    public function a_withdrawn_course_generates_nothing(): void
    {
        $course = $this->course();
        $course->forceFill(['withdrawn_at' => now()])->save();

        $this->expectException(CourseNotOpen::class);

        $this->generator()->first($course);
    }

    private function generator(): SyllabusGenerator
    {
        return app(SyllabusGenerator::class);
    }

    private function course(bool $withCriteria = true): Course
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->create();

        $tree = app(MemoryTree::class);
        $tree->put($course->student, $course->workingKnowledgePath(), self::WK);

        if ($withCriteria) {
            $tree->put($course->student, CourseCriteria::pathFor($course), self::CRITERIA);
        }

        return $course;
    }

    /**
     * @param  list<array<string, mixed>|string>  $responses
     */
    private function scriptModel(array $responses): void
    {
        $this->sent = [];
        $queue = $responses;

        $this->fakeModel(function (string $prompt) use (&$queue): array|string {
            $this->sent[] = $prompt;

            return array_shift($queue) ?? 'nothing scripted';
        });
    }
}
