<?php

namespace Tests\Feature\Assessment;

use App\Assessment\HomeworkResults;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Lessons\CompleteLesson;
use App\Memory\MemoryTree;
use App\Models\Lesson;
use App\Models\Memory;
use App\Models\MemoryVersion;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.2 -- homework served, answered, and fed to the next WK update.
 *
 * The generation half rides 5.4.1's chain and already passed with package 5.
 * What is under test here is the other three quarters: that the row can be
 * served without leaking its key, that answering it records an attempt, and
 * that the attempt reaches the next `wk.update` -- the done-condition, "a
 * homework attempt visible in WK diff".
 */
class HomeworkTest extends AssessmentTestCase
{
    #[Test]
    public function homework_is_served_with_its_reading_and_without_its_key(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        $props = $this->screen($lesson);

        $this->assertSame('ready', $props['state']);
        $this->assertSame('Letters as numbers', $props['homework']['scope']);
        $this->assertSame(
            'Khan Academy: intro to variables',
            $props['homework']['suggested_reading'][0]['title'],
        );
        $this->assertFalse($props['homework']['progress'][0]['answered']);

        foreach ($props['homework']['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_option_id', $question);
            $this->assertArrayNotHasKey('explanation', $question);
        }
    }

    #[Test]
    public function homework_that_the_chain_has_not_written_yet_is_pending_not_missing(): void
    {
        $lesson = $this->lesson();

        $props = $this->screen($lesson);

        $this->assertSame('pending', $props['state']);
        $this->assertNull($props['homework']);
    }

    #[Test]
    public function another_student_may_not_read_or_answer_this_homework(): void
    {
        $lesson = $this->completeLesson($this->lesson());
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/lessons/{$lesson->id}/homework")->assertForbidden();

        $this->actingAs($stranger)
            ->postJson("/lessons/{$lesson->id}/homework/answers", ['question_id' => 'h1', 'option_id' => 'a'])
            ->assertForbidden();
    }

    #[Test]
    public function answering_records_an_attempt_and_returns_the_key(): void
    {
        $lesson = $this->completeLesson($this->lesson());
        $quiz = app(Quizzes::class)->existing(QuizKind::Homework, $lesson);

        $this->actingAs($lesson->course->student)
            ->postJson("/lessons/{$lesson->id}/homework/answers", ['question_id' => 'h1', 'option_id' => 'b'])
            ->assertOk()
            ->assertJson(['correct' => false, 'correct_option_id' => 'a', 'explanation' => '"More than" is addition.']);

        $this->assertSame(1, $quiz->attempts()->count());

        // A reload shows the verdict but not the explanation again -- one
        // disclosure point, the same rule 5.2.3 holds for checkpoints.
        $progress = $this->screen($lesson)['homework']['progress'][0];

        $this->assertSame(['question_id' => 'h1', 'answered' => true, 'correct' => false, 'attempts' => 1], $progress);
    }

    #[Test]
    public function an_answer_to_a_question_the_homework_does_not_ask_is_a_422(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        $this->actingAs($lesson->course->student)
            ->postJson("/lessons/{$lesson->id}/homework/answers", ['question_id' => 'nope', 'option_id' => 'a'])
            ->assertStatus(422);
    }

    /**
     * S8's props (spec 8.9.1). The route answers a page rather than JSON since
     * package 8 built the screen; what it carries is unchanged.
     *
     * @return array<string, mixed>
     */
    private function screen(Lesson $lesson): array
    {
        return $this->inertiaProps(
            $this->actingAs($lesson->course->student)
                ->get("/lessons/{$lesson->id}/homework")
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('lessons/homework'))
        );
    }

    #[Test]
    public function the_homework_attempt_is_visible_in_the_working_knowledge_diff(): void
    {
        $course = $this->activeCourse();
        $first = $this->completeLesson($this->lesson($course, 1));

        // The student does their homework wrong, between the two lessons.
        $this->actingAs($course->student)
            ->postJson("/lessons/{$first->id}/homework/answers", ['question_id' => 'h1', 'option_id' => 'b'])
            ->assertOk();

        $second = $this->lesson($course, 2);
        $this->teachLesson($second);

        $this->scriptModel([
            $this->summaryResponse($this->conceptsOf($second)),
            self::WK."\n\n## Shaky\n\n- Writing expressions: missed the homework question on \"five more than a number\".",
            $this->quizResponse(),
        ]);

        app(CompleteLesson::class)($second->fresh());

        // What the model was told -- the homework verdict reached `wk.update`.
        $update = $this->promptContaining("The student's homework record in this subtopic");

        $this->assertStringContainsString('### Homework for *Letters as Numbers*', $update);
        $this->assertStringContainsString('wrong first time', $update);
        $this->assertStringContainsString('(concept: expression)', $update);

        // And what came back is a new version of the document, so the change
        // is a diff someone can take rather than an overwrite.
        $versions = $this->versionsOf($course->student, $course->workingKnowledgePath());

        $this->assertGreaterThan(1, count($versions));
        $this->assertStringNotContainsString('five more than a number', $versions[count($versions) - 2]);
        $this->assertStringContainsString('five more than a number', $versions[count($versions) - 1]);
    }

    #[Test]
    public function unanswered_homework_and_the_lessons_own_are_left_out_of_the_record(): void
    {
        $course = $this->activeCourse();
        $first = $this->completeLesson($this->lesson($course, 1));
        $second = $this->completeLesson($this->lesson($course, 2));

        // Nothing has been answered, so there is nothing to report -- an
        // untouched homework says nothing about what the student knows.
        $this->assertSame(
            'The student has not done any homework in this subtopic yet.',
            HomeworkResults::forSubtopicOf($second)->render(),
        );

        $this->actingAs($course->student)
            ->postJson("/lessons/{$first->id}/homework/answers", ['question_id' => 'h1', 'option_id' => 'a'])
            ->assertOk();

        $this->actingAs($course->student)
            ->postJson("/lessons/{$second->id}/homework/answers", ['question_id' => 'h1', 'option_id' => 'a'])
            ->assertOk();

        // Lesson 2's own homework is excluded from lesson 2's update; lesson
        // 1's is the evidence that update has to fold in.
        $rows = HomeworkResults::forSubtopicOf($second)->rows();

        $this->assertSame(['Letters as Numbers'], array_values(array_unique(
            array_column($rows, 'lesson_title'),
        )));
    }

    #[Test]
    public function an_ungradable_homework_stops_the_chain_rather_than_being_stored(): void
    {
        $lesson = $this->lesson();
        $this->teachLesson($lesson);

        $broken = $this->quizResponse();
        $broken['quiz']['questions'][0]['correct_option_id'] = 'nonexistent';

        $this->scriptModel([
            $this->summaryResponse($this->conceptsOf($lesson)),
            self::WK,
            $broken,
        ]);

        app(CompleteLesson::class)($lesson->fresh());

        // The lesson still completed -- 5.4.5 -- but no unanswerable homework
        // was stored, and the chain parked for `lessons:retry-pipeline`.
        $this->assertTrue($lesson->fresh()->isCompleted());
        $this->assertSame(0, $lesson->quizzes()->ofKind(QuizKind::Homework)->count());
        $this->assertTrue($lesson->fresh()->isPipelineParked());
    }

    #[Test]
    public function a_concept_tag_the_syllabus_does_not_declare_is_dropped_from_homework(): void
    {
        $lesson = $this->lesson();
        $this->teachLesson($lesson);

        $invented = $this->quizResponse();
        $invented['quiz']['questions'][0]['concept_tag'] = 'quadratic-formula';

        $this->scriptModel([
            $this->summaryResponse($this->conceptsOf($lesson)),
            self::WK,
            $invented,
        ]);

        app(CompleteLesson::class)($lesson->fresh());

        $quiz = app(Quizzes::class)->existing(QuizKind::Homework, $lesson->fresh());

        $this->assertNull($quiz->payload['quiz']['questions'][0]['concept_tag']);
    }

    /**
     * The one prompt this test's model was sent containing a phrase.
     */
    private function promptContaining(string $needle): string
    {
        foreach ($this->sent as $prompt) {
            if (str_contains($prompt, $needle)) {
                return $prompt;
            }
        }

        $this->fail("No prompt containing [{$needle}] was sent.");
    }

    /**
     * Every version of a document, oldest first.
     *
     * Reads `memory_versions` directly, which production code may not do
     * (spec 3.2) -- a diff across versions is exactly what the path API does
     * not expose, and asserting on it is the point of this test.
     *
     * @return list<string>
     */
    private function versionsOf(User $student, string $path): array
    {
        $this->assertNotNull(app(MemoryTree::class)->get($student, $path));

        $memory = Memory::query()->where('user_id', $student->id)->where('path', $path)->firstOrFail();

        return MemoryVersion::query()
            ->where('memory_id', $memory->id)
            ->orderBy('version')
            ->pluck('content')
            ->all();
    }
}
