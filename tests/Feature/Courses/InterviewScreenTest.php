<?php

namespace Tests\Feature\Courses;

use App\Ai\GatewayAgent;
use App\Courses\CourseStatus;
use App\Courses\Interviews\InterviewKind;
use App\Courses\Interviews\InterviewSession;
use App\Courses\Interviews\Speaker;
use App\Courses\Interviews\Transcript;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\InterviewMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.7.2 and 8.7.3 -- screens S3 and S4.
 *
 * Both done-conditions are here: **the transcript survives a reload**, and
 * **Done writes the document**. The first is the one that matters, because the
 * way to fail it is silent -- `InterviewSession::open()` discards the
 * transcript, so a screen that re-opens on every visit loses the whole
 * interview and looks fine doing it.
 *
 * So the re-open precondition is tested from both sides: the screen refuses to
 * offer it, and the engine refuses to obey it.
 */
class InterviewScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function the_transcript_survives_a_reload(): void
    {
        $course = $this->course();
        $this->converse($course);

        $this->actingAs($course->student)
            ->get(route('courses.knowledge-interview.show', $course))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('courses/interview')
                ->where('kind', 'knowledge')
                ->has('turns', 2)
                ->where('turns.0.speaker', 'tutor')
                ->where('turns.1.content', 'I can order a coffee.')
                ->where('may_open', false)
                ->where('limits.answer_characters', InterviewSession::MAX_ANSWER),
            );
    }

    /**
     * The precondition of 8.6, on the wire.
     */
    #[Test]
    public function the_stream_url_is_refused_once_the_tutor_has_spoken(): void
    {
        $course = $this->course();
        $this->converse($course);

        $before = InterviewMessage::query()->count();

        $this->actingAs($course->student)
            ->get(route('courses.knowledge-interview.stream', $course))
            ->assertStatus(422);

        $this->assertSame($before, InterviewMessage::query()->count(), 'A refused re-open must not have discarded anything.');
    }

    /**
     * The recovery re-opening exists for: a connection dropped during the
     * tutor's very first question. Nothing the student typed is at risk,
     * because there is nothing there to be at risk (spec 4.6).
     */
    #[Test]
    public function an_interview_the_tutor_never_answered_may_be_opened_again(): void
    {
        $course = $this->course(CourseStatus::KnowledgeInterview);

        $this->actingAs($course->student)
            ->get(route('courses.knowledge-interview.show', $course))
            ->assertInertia(fn ($page) => $page->where('may_open', true));
    }

    /**
     * Done on a conversation nobody had would summarize nothing over whatever
     * earlier courses in the subtopic recorded (ledger D7a).
     */
    #[Test]
    public function done_refuses_a_transcript_the_tutor_never_spoke_in(): void
    {
        $course = $this->course(CourseStatus::KnowledgeInterview);

        $this->actingAs($course->student)
            ->post(route('courses.knowledge-interview.complete', $course))
            ->assertRedirect(route('courses.knowledge-interview.show', $course));

        $this->assertSame(CourseStatus::KnowledgeInterview, $course->fresh()?->status);
        $this->assertNull((new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md'));
    }

    #[Test]
    public function done_writes_the_working_knowledge_document_and_moves_the_course_on(): void
    {
        $course = $this->course();
        $this->converse($course);

        $this->actingAs($course->student)
            ->post(route('courses.knowledge-interview.complete', $course))
            ->assertRedirect(route('courses.goals-interview.show', $course));

        $this->assertSame(CourseStatus::GoalsInterview, $course->fresh()?->status);
        $this->assertStringContainsString(
            'Solid',
            (string) (new MemoryTree)->get($course->student, 'math/algebra/WORKING_KNOWLEDGE.md'),
        );
        $this->assertSame(0, InterviewMessage::query()->count(), 'The transcript goes when the document lands (4.3).');
    }

    /**
     * 8.7.3 -- "same shape", and the same three URLs one segment along.
     */
    #[Test]
    public function the_goals_interview_is_the_same_screen_writing_criteria(): void
    {
        $course = $this->course(CourseStatus::GoalsInterview);
        $this->converse($course, InterviewKind::Goals);

        $this->actingAs($course->student)
            ->get(route('courses.goals-interview.show', $course))
            ->assertInertia(fn ($page) => $page
                ->component('courses/interview')
                ->where('kind', 'goals')
                ->has('turns', 2)
                ->where('urls.complete', route('courses.goals-interview.complete', $course)),
            );

        $this->actingAs($course->student)
            ->post(route('courses.goals-interview.complete', $course))
            ->assertRedirect(route('courses.syllabus.show', $course));

        $this->assertStringContainsString(
            'Solid',
            (string) (new MemoryTree)->get($course->student, 'math/algebra/101/COURSE_CRITERIA.md'),
        );
    }

    /**
     * The resume table (8.6): a course that has moved past this interview is
     * not shown a screen it can do nothing with.
     */
    #[Test]
    public function a_course_that_has_moved_on_is_redirected_to_where_it_now_stands(): void
    {
        $course = $this->course(CourseStatus::SyllabusDraft);

        $this->actingAs($course->student)
            ->get(route('courses.knowledge-interview.show', $course))
            ->assertRedirect(route('courses.syllabus.show', $course));
    }

    /**
     * Spec 10.2 -- a stale tab answering an interview the course has left.
     * A refusal, not a failure: the student is sent where the course is.
     */
    #[Test]
    public function an_answer_to_a_finished_interview_is_sent_where_the_course_now_stands(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();
        $course = $this->course(CourseStatus::GoalsInterview);

        $this->actingAs($course->student)
            ->post(route('courses.knowledge-interview.answers.store', $course), ['text' => 'One more thing.'])
            ->assertRedirect(route('courses.goals-interview.show', $course));

        $this->assertSame(0, InterviewMessage::query()->count());
    }

    #[Test]
    public function no_one_may_read_another_students_interview(): void
    {
        $course = $this->course();

        $this->actingAs(User::factory()->create())
            ->get(route('courses.knowledge-interview.show', $course))
            ->assertForbidden();
    }

    private function course(CourseStatus $status = CourseStatus::Created): Course
    {
        return Course::factory()->for(User::factory(), 'student')->status($status)->create();
    }

    /**
     * A transcript that has actually been had: a question and one answer.
     */
    private function converse(Course $course, InterviewKind $kind = InterviewKind::Knowledge): void
    {
        $this->scriptTutor();

        $transcript = new Transcript($course, $kind);
        $transcript->append(Speaker::Tutor, 'What can you do already?');
        $transcript->append(Speaker::Student, 'I can order a coffee.');

        if ($course->status === CourseStatus::Created) {
            $course->forceFill(['status' => CourseStatus::KnowledgeInterview])->save();
        }
    }

    /**
     * A model that only ever has to write the summarizing document here --
     * the conversation itself is seeded rather than streamed, because what
     * these tests are about is what survives between requests.
     */
    private function scriptTutor(): void
    {
        GatewayAgent::fake(fn (): string => "## Solid\n\n- Orders a coffee.\n\n## Shaky\n\n- Everything else.\n\n## Gaps\n\n- The rest of the subtopic.")
            ->preventStrayPrompts();
    }
}
