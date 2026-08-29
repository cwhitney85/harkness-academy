<?php

namespace Tests\Feature\Assessment;

use App\Assessment\Exceptions\RefresherNotAvailable;
use App\Assessment\Exceptions\RefresherUnusable;
use App\Assessment\QuizKind;
use App\Assessment\Refreshers;
use App\Lessons\SummaryDocument;
use App\Memory\MemoryTree;
use App\Models\Quiz;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.4 -- "lesson refresher questions traceable to its summary".
 *
 * Traceability is the whole content of this item. A refresher that asks about
 * something the student was never shown reads as the system having lost track
 * of them, which is worse than no refresher at all -- so the claim is checked
 * in code rather than asked for in the prompt, and these tests are what hold
 * that check honest.
 */
class RefresherTest extends AssessmentTestCase
{
    #[Test]
    public function a_lesson_refresher_reads_that_lessons_summary_and_nothing_else(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        $this->scriptModel([$this->refresherResponse(['variable', 'expression'])]);

        $quiz = app(Refreshers::class)->forLesson($lesson);

        $this->assertSame(QuizKind::Refresher, $quiz->kind);
        $this->assertSame($lesson->getMorphClass(), $quiz->quizzable_type);
        $this->assertSame($lesson->id, $quiz->quizzable_id);

        $prompt = $this->sent[0];

        // The one document, named by its path so the model knows it is reading
        // a record of this student.
        $this->assertStringContainsString('### `'.SummaryDocument::pathFor($lesson).'`', $prompt);
        $this->assertStringContainsString('## Concepts', $prompt);
        $this->assertStringContainsString('the lesson "Letters as Numbers"', $prompt);

        // Not the working-knowledge document -- including it would make richer
        // questions and break the claim this grain exists to support.
        $this->assertStringNotContainsString('Reads graphs confidently', $prompt);
    }

    #[Test]
    public function every_question_is_traceable_to_a_concept_the_summary_records(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        $this->scriptModel([$this->refresherResponse(['variable', 'coefficient'])]);

        $quiz = app(Refreshers::class)->forLesson($lesson);

        $recorded = $lesson->fresh()->summary()->slugs();

        foreach ($quiz->payload['quiz']['questions'] as $question) {
            $this->assertContains($question['concept_tag'], $recorded);
        }
    }

    #[Test]
    public function a_question_about_something_the_summary_never_recorded_is_refused(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        // 'quadratic-formula' is not in this lesson's summary, so its tag is
        // resolved to null and the question becomes untraceable.
        $strayed = $this->refresherResponse(['variable', 'quadratic-formula']);

        $this->scriptModel([$strayed, $strayed]);

        try {
            app(Refreshers::class)->forLesson($lesson);
            $this->fail('A refresher that strays outside its summary should be refused.');
        } catch (RefresherUnusable $e) {
            $this->assertStringContainsString('do not record', $e->getMessage());
        }

        $this->assertSame(0, Quiz::query()->ofKind(QuizKind::Refresher)->count());
    }

    #[Test]
    public function a_lesson_with_no_summary_has_nothing_to_refresh(): void
    {
        $lesson = $this->lesson();

        $this->expectException(RefresherNotAvailable::class);

        app(Refreshers::class)->forLesson($lesson);
    }

    #[Test]
    public function a_subtopic_refresher_reads_the_working_knowledge_and_every_summary(): void
    {
        $course = $this->taughtCourse();

        $this->scriptModel([$this->refresherResponse(['variable', 'equation'])]);

        $quiz = app(Refreshers::class)->forSubtopic($course);

        $this->assertSame($course->getMorphClass(), $quiz->quizzable_type);
        $this->assertSame($course->id, $quiz->quizzable_id);

        $prompt = $this->sent[0];

        $this->assertStringContainsString('### `'.$course->workingKnowledgePath().'`', $prompt);

        foreach ($course->lessons as $lesson) {
            $this->assertStringContainsString('### `'.SummaryDocument::pathFor($lesson).'`', $prompt);
        }

        $this->assertStringContainsString('the subtopic "'.$course->subtopic_title.'"', $prompt);
    }

    #[Test]
    public function a_subtopic_refresher_may_draw_on_a_concept_from_any_of_its_courses(): void
    {
        $course = $this->taughtCourse();

        // `inverse-operation` belongs to lesson 2, not lesson 1 -- the
        // subtopic vocabulary is the union, the way ErosionGuard's is (D8).
        $this->scriptModel([$this->refresherResponse(['inverse-operation'])]);

        $quiz = app(Refreshers::class)->forSubtopic($course);

        $this->assertSame('inverse-operation', $quiz->payload['quiz']['questions'][0]['concept_tag']);
    }

    #[Test]
    public function a_subtopic_with_no_recorded_knowledge_has_nothing_to_refresh(): void
    {
        $course = $this->activeCourse();

        app(MemoryTree::class)->put($course->student, $course->workingKnowledgePath(), '   ');

        $this->expectException(RefresherNotAvailable::class);

        app(Refreshers::class)->forSubtopic($course->refresh());
    }

    #[Test]
    public function coming_back_next_month_asks_new_questions(): void
    {
        $lesson = $this->completeLesson($this->lesson());

        $this->scriptModel([
            $this->refresherResponse(['variable']),
            $this->refresherResponse(['expression']),
        ]);

        $first = app(Refreshers::class)->forLesson($lesson);
        $second = app(Refreshers::class)->forLesson($lesson);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('variable', $first->payload['quiz']['questions'][0]['concept_tag']);
        $this->assertSame('expression', $second->payload['quiz']['questions'][0]['concept_tag']);
    }

    #[Test]
    public function a_refresher_is_served_stripped_and_answered_like_a_checkpoint(): void
    {
        $lesson = $this->completeLesson($this->lesson());
        $this->scriptModel([$this->refresherResponse(['variable'])]);

        $created = $this->actingAs($lesson->course->student)
            ->postJson("/lessons/{$lesson->id}/refreshers")
            ->assertCreated()
            ->json();

        foreach ($created['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_option_id', $question);
            $this->assertArrayNotHasKey('explanation', $question);
        }

        $this->actingAs($lesson->course->student)
            ->getJson("/refreshers/{$created['quiz_id']}")
            ->assertOk()
            ->assertJsonPath('quiz_id', $created['quiz_id']);

        // Answering teaches: a refresher gates nothing, so the key is the
        // point of answering.
        $this->actingAs($lesson->course->student)
            ->postJson("/refreshers/{$created['quiz_id']}/answers", [
                'question_id' => 'r-variable',
                'option_id' => 'b',
            ])
            ->assertOk()
            ->assertJson(['correct' => false, 'correct_option_id' => 'a']);
    }

    #[Test]
    public function a_quiz_that_is_not_a_refresher_is_not_reachable_by_that_url(): void
    {
        $lesson = $this->completeLesson($this->lesson());
        $homework = $lesson->quizzes()->ofKind(QuizKind::Homework)->firstOrFail();

        $this->actingAs($lesson->course->student)
            ->getJson("/refreshers/{$homework->id}")
            ->assertNotFound();

        $this->actingAs($lesson->course->student)
            ->postJson("/refreshers/{$homework->id}/answers", ['question_id' => 'h1', 'option_id' => 'a'])
            ->assertNotFound();
    }

    #[Test]
    public function another_student_may_not_generate_read_or_answer_this_refresher(): void
    {
        $lesson = $this->completeLesson($this->lesson());
        $this->scriptModel([$this->refresherResponse(['variable'])]);
        $quiz = app(Refreshers::class)->forLesson($lesson);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson("/lessons/{$lesson->id}/refreshers")->assertForbidden();
        $this->actingAs($stranger)->postJson("/courses/{$lesson->course_id}/refreshers")->assertForbidden();
        $this->actingAs($stranger)->getJson("/refreshers/{$quiz->id}")->assertForbidden();

        $this->actingAs($stranger)
            ->postJson("/refreshers/{$quiz->id}/answers", ['question_id' => 'r-variable', 'option_id' => 'a'])
            ->assertForbidden();
    }
}
