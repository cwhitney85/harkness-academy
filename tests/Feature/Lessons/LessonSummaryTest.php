<?php

namespace Tests\Feature\Lessons;

use App\Lessons\CompleteLesson;
use App\Lessons\Exceptions\LessonSummaryUnusable;
use App\Lessons\LessonSections;
use App\Lessons\LessonSummary;
use App\Lessons\Mastery;
use App\Lessons\Pipeline\CompletionPipeline;
use App\Lessons\SummaryDocument;
use App\Memory\MemoryTree;
use App\Models\Lesson;
use App\Models\LessonPipelineStep;
use App\Models\Quiz;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.4.2 and 5.4.3 -- the summary contract and the document it becomes.
 *
 * The done-conditions: an undeclared slug fails validation, the render matches
 * a golden file, and a refresher fixture can read SUMMARY.md back out of the
 * tree. The last one is package 6.4's front door, tested here because 5.4.3
 * is what has to be true for 6.4 to exist at all.
 */
class LessonSummaryTest extends SectionStreamTestCase
{
    private const UPDATED_WK = "## Solid\n\n- Reads graphs confidently.\n- variable, expression, coefficient.\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- Equations.";

    #[Test]
    public function a_slug_the_lesson_never_declared_fails_validation(): void
    {
        $lesson = $this->taughtLesson();
        $sections = LessonSections::of($lesson);

        $this->expectException(LessonSummaryUnusable::class);
        $this->expectExceptionMessage('quadratic-formula');

        // 5.4.2's done-condition. An invented slug would flow into the WK
        // update and from there into a refresher quiz keyed on a concept no
        // lesson taught.
        LessonSummary::fromModel(
            $this->summaryResponse(['variable', 'quadratic-formula']),
            $lesson,
            $sections,
        );
    }

    #[Test]
    public function a_slug_from_another_lesson_of_the_same_course_is_still_undeclared(): void
    {
        $lesson = $this->taughtLesson();

        // `inverse-operation` is declared by lesson 2, so it is a real slug in
        // this course's vocabulary -- and still not something lesson 1 taught.
        $this->expectException(LessonSummaryUnusable::class);

        LessonSummary::fromModel(
            $this->summaryResponse(['variable', 'inverse-operation']),
            $lesson,
            LessonSections::of($lesson),
        );
    }

    #[Test]
    public function a_concept_reported_twice_is_refused(): void
    {
        $lesson = $this->taughtLesson();

        $this->expectException(LessonSummaryUnusable::class);
        $this->expectExceptionMessage('reported twice');

        LessonSummary::fromModel(
            $this->summaryResponse(['variable', 'variable']),
            $lesson,
            LessonSections::of($lesson),
        );
    }

    #[Test]
    public function a_summary_of_only_declared_slugs_is_accepted_and_readable(): void
    {
        $lesson = $this->taughtLesson();

        $summary = LessonSummary::fromModel(
            $this->summaryResponse(['variable', 'expression']),
            $lesson,
            LessonSections::of($lesson),
        );

        $this->assertSame(['variable', 'expression'], $summary->slugs());
        $this->assertSame(Mastery::Solid, $summary->concepts()[0]['mastery']);
        $this->assertSame(['Still writes 3n as n3 about half the time.'], $summary->struggles());
        $this->assertStringContainsString('shopping list', $summary->prose());
    }

    #[Test]
    public function the_rendered_document_matches_the_golden_file(): void
    {
        Carbon::setTestNow('2026-08-25 09:00:00');

        $lesson = $this->completedLesson();

        $rendered = app(MemoryTree::class)->get($lesson->course->student, SummaryDocument::pathFor($lesson));

        $this->assertSame(
            file_get_contents(base_path('tests/fixtures/summary/letters-as-numbers.md')),
            $rendered,
        );
    }

    #[Test]
    public function the_document_sits_beside_the_lesson_guidance(): void
    {
        $lesson = $this->completedLesson();

        $this->assertSame(
            $lesson->course->materialsPrefix().'/01-letters-as-numbers/SUMMARY.md',
            SummaryDocument::pathFor($lesson),
        );

        $paths = app(MemoryTree::class)->list($lesson->course->student, $lesson->course->materialsPrefix());

        $this->assertContains($lesson->guidance_path, $paths);
        $this->assertContains(SummaryDocument::pathFor($lesson), $paths);
    }

    #[Test]
    public function a_refresher_reads_the_document_and_finds_the_lessons_concepts(): void
    {
        $lesson = $this->completedLesson();

        // Standing in for spec 6.4: a refresher is built from `scope_docs`,
        // which for lesson grain is exactly this document.
        $scopeDocs = app(MemoryTree::class)->get($lesson->course->student, SummaryDocument::pathFor($lesson));

        $this->assertNotNull($scopeDocs);

        foreach ($lesson->summary()->slugs() as $slug) {
            $this->assertStringContainsString($slug, $scopeDocs, "SUMMARY.md must record [{$slug}] for 6.4 to quiz on it.");
        }

        $this->assertStringContainsString('Still writes 3n as n3', $scopeDocs);
        $this->assertStringContainsString('Can a letter stand for more than one number?', $scopeDocs);
    }

    #[Test]
    public function a_completed_summary_is_never_rewritten(): void
    {
        $lesson = $this->completedLesson();

        $before = app(MemoryTree::class)->document(
            $lesson->course->student,
            SummaryDocument::pathFor($lesson),
        );

        // Nothing scripted: the summarize step must not call the model again.
        $this->scriptModel([]);

        app(CompletionPipeline::class)->resume($lesson->fresh());

        $after = app(MemoryTree::class)->document($lesson->course->student, SummaryDocument::pathFor($lesson));

        $this->assertSame($before->versionId, $after->versionId, 'Spec 5.4.3: completed summaries are immutable.');
        $this->assertSame(4, LessonPipelineStep::query()->count());
    }

    /**
     * Both sections taught, so the lesson is completable.
     */
    private function taughtLesson(): Lesson
    {
        $this->scriptModel([
            'A variable is a letter standing in for a number.',
            $this->checkpointResponse(1),
            'A coefficient is the number in front of the letter.',
            $this->checkpointResponse(2),
        ]);

        $first = [];
        $this->teach(1, $first);

        $second = [];
        $this->teach(2, $second);

        return $this->lesson->fresh();
    }

    /**
     * Taught, completed, and through the whole chain.
     */
    private function completedLesson(): Lesson
    {
        $lesson = $this->taughtLesson();

        $this->scriptModel([$this->summaryResponse(), self::UPDATED_WK, $this->quizResponse()]);

        app(CompleteLesson::class)($lesson);

        $this->assertSame(1, Quiz::query()->where('kind', 'homework')->count());

        return $lesson->fresh();
    }
}
