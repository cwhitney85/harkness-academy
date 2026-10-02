<?php

namespace Tests\Feature\Lessons;

use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\SkillLevel;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Lessons\CompleteLesson;
use App\Lessons\LessonSections;
use App\Lessons\LessonSessions;
use App\Lessons\Pipeline\ErosionGuard;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\MemoryVersion;
use Database\Factories\CourseSyllabusFactory;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.4.4 -- `wk.update` is a full-document replacement, and the erosion
 * guard is what makes that survivable.
 *
 * The risk this exists for: the model receives the whole working-knowledge
 * document and returns the whole document, so every previously recorded
 * concept is at stake on every single lesson. A slug quietly dropped erases a
 * course's worth of evidence, and nothing downstream notices, because the next
 * update reads the eroded document as if it were the truth.
 *
 * The three-lesson sequence is the regression the done-condition asks for: two
 * honest updates that carry everything forward, then one that forgets, caught.
 */
class WorkingKnowledgeErosionTest extends LessonEngineTestCase
{
    private const START = "## Solid\n\n- Reads graphs confidently.\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- variable, expression, coefficient, equation, inverse-operation.";

    #[Test]
    public function three_honest_updates_carry_every_concept_forward(): void
    {
        $course = $this->threeLessonCourse();
        $tree = app(MemoryTree::class);

        Log::spy();

        foreach ([1, 2, 3] as $position) {
            $this->runLesson($course, $position, forget: []);
        }

        $final = $tree->get($course->student, $course->workingKnowledgePath());

        foreach (['variable', 'expression', 'coefficient', 'equation', 'inverse-operation'] as $slug) {
            $this->assertStringContainsString($slug, (string) $final, "Lesson 3's update lost [{$slug}].");
        }

        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function an_unrelated_lesson_that_drops_an_earlier_concept_is_caught(): void
    {
        $course = $this->threeLessonCourse();

        $this->runLesson($course, 1, forget: []);
        $this->runLesson($course, 2, forget: []);

        Log::spy();

        // Lesson 3 is about `inverse-operation` and has no business touching
        // what lesson 1 established -- which is exactly the erosion 5.4.4 is
        // about.
        $this->runLesson($course, 3, forget: ['variable', 'coefficient']);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'dropped concepts')
                && $context['lost'] === ['coefficient', 'variable'];
        })->once();
    }

    #[Test]
    public function the_previous_version_is_still_there_to_diff_against(): void
    {
        $course = $this->threeLessonCourse();
        $tree = app(MemoryTree::class);

        $this->runLesson($course, 1, forget: []);
        $this->runLesson($course, 2, forget: ['variable']);

        $path = $course->workingKnowledgePath();

        // 5.4.4: "version history makes erosion diffable". The guard reports
        // rather than blocks precisely because this is recoverable.
        $versions = MemoryVersion::query()
            ->join('memories', 'memories.id', '=', 'memory_versions.memory_id')
            ->where('memories.user_id', $course->user_id)
            ->where('memories.path', $path)
            ->orderBy('memory_versions.version')
            ->pluck('memory_versions.content')
            ->all();

        $this->assertCount(3, $versions, 'The seed plus two lesson updates.');
        $this->assertStringContainsString('variable', $versions[1]);
        $this->assertStringNotContainsString('variable', $versions[2]);

        $this->assertStringNotContainsString(
            'variable',
            (string) $tree->get($course->student, $path),
            'The eroded document is still what was written -- the guard reports, it does not block.',
        );
    }

    #[Test]
    public function the_vocabulary_spans_every_course_in_the_subtopic(): void
    {
        $course = $this->threeLessonCourse();

        // WK accumulates across levels (ledger D8), so an earlier course's
        // concepts are at risk from this course's updates too.
        // Completed, because only one course per subtopic may be open at a
        // time (ledger D7a) -- which is the same rule that makes them share
        // one working-knowledge document.
        $earlier = Course::factory()
            ->for($course->student, 'student')
            ->completed()
            ->create(['skill_level' => SkillLevel::Intermediate]);

        CourseSyllabus::factory()->for($earlier)->approved()->create([
            'syllabus_json' => $this->syllabusWithConcepts(['long-division']),
        ]);

        $slugs = ErosionGuard::vocabulary($course->lessons()->first());

        $this->assertContains('long-division', $slugs);
        $this->assertContains('variable', $slugs);
    }

    /**
     * A course whose syllabus has three one-section lessons.
     */
    private function threeLessonCourse(): Course
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->create();

        $tree = app(MemoryTree::class);
        $tree->put($course, $course->workingKnowledgePath(), self::START);
        $tree->put($course, CourseCriteria::pathFor($course), self::CRITERIA);

        app(ApproveSyllabus::class)($course, CourseSyllabus::factory()->for($course)->create([
            'syllabus_json' => $this->threeLessonSyllabus(),
        ]));

        return $course->refresh();
    }

    /**
     * Teach, complete and summarize one lesson, with a model that revises the
     * document it was handed -- optionally forgetting some of it.
     *
     * @param  list<string>  $forget
     */
    private function runLesson(Course $course, int $position, array $forget): void
    {
        $lesson = $course->lessons()->where('position', $position)->firstOrFail();
        $slugs = LessonSections::of($lesson)->declaredConcepts();

        $this->scriptModel([
            "Teaching section 1 of lesson {$position}.",
            $this->checkpointResponse(1, $slugs[0]),
            $this->summaryResponse($slugs),
            $this->reviseWorkingKnowledge($course, $slugs, $forget),
            $this->quizResponse(),
        ]);

        $frames = [];
        app(LessonSessions::class)->for($lesson)->teachSection(1, $this->collectingStream($frames));

        app(CompleteLesson::class)($lesson->fresh());
    }

    /**
     * Stand in for the model rewriting the whole document: carry everything
     * the prior version recorded, add this lesson's concepts, and drop
     * whatever the test told it to forget.
     *
     * @param  list<string>  $slugs
     * @param  list<string>  $forget
     */
    private function reviseWorkingKnowledge(Course $course, array $slugs, array $forget): string
    {
        $prior = (string) app(MemoryTree::class)->get($course->student, $course->workingKnowledgePath());

        $carried = [];

        foreach (ErosionGuard::vocabulary($course->lessons()->first()) as $slug) {
            if (in_array($slug, $forget, true)) {
                continue;
            }

            if (str_contains($prior, $slug) || in_array($slug, $slugs, true)) {
                $carried[] = "- {$slug}: recorded.";
            }
        }

        return "## Solid\n\n".implode("\n", $carried)."\n\n## Shaky\n\n- Negative signs.\n\n## Gaps\n\n- The rest of the subtopic.";
    }

    /**
     * @return array<string, mixed>
     */
    private function threeLessonSyllabus(): array
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();

        $syllabus['lessons'] = [
            $this->syllabusLesson(1, 'Letters as Numbers', ['variable', 'coefficient']),
            $this->syllabusLesson(2, 'Building Expressions', ['expression']),
            $this->syllabusLesson(3, 'Undoing an Operation', ['inverse-operation', 'equation']),
        ];

        $syllabus['final_exam_concepts'] = ['variable', 'expression', 'equation'];

        return $syllabus;
    }

    /**
     * @param  list<string>  $concepts
     * @return array<string, mixed>
     */
    private function syllabusLesson(int $position, string $title, array $concepts): array
    {
        return [
            'position' => $position,
            'title' => $title,
            'objective' => "Work with {$title}.",
            'sections' => [['title' => $title, 'concepts' => $concepts]],
            'homework_focus' => "Practice {$title}.",
            'estimated_minutes' => 20,
        ];
    }

    /**
     * @param  list<string>  $concepts
     * @return array<string, mixed>
     */
    private function syllabusWithConcepts(array $concepts): array
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();

        $syllabus['lessons'] = [$this->syllabusLesson(1, 'Earlier Course', $concepts)];
        $syllabus['final_exam_concepts'] = $concepts;

        return $syllabus;
    }
}
