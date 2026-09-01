<?php

namespace Tests\Feature\Lessons;

use App\Courses\LessonStatus;
use App\Lessons\LessonSession;
use App\Lessons\SelectionExplainer;
use App\Models\Course;
use App\Models\Lesson;
use Database\Seeders\CourseCatalogSeeder;
use Database\Seeders\InterfaceFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.8 -- screen S6, and S7 as a state of it.
 *
 * Read against 8.2's fixture rather than against a lesson built here, because
 * the states the screen has to render are exactly the ones the fixture exists
 * to hold: a lesson stopped halfway through with an unanswered checkpoint in
 * front of the student, a lesson finished with its homework untouched, and the
 * lessons behind and ahead of both.
 *
 * What is asserted is what the server can settle. Streaming, the margin
 * checkpoint at 390px and the explainer strip are the browser's half of these
 * done-conditions and were checked at 1440px and 390px in the session.
 */
class LessonScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');

        $this->seed([CourseCatalogSeeder::class, InterfaceFixtureSeeder::class]);
    }

    /**
     * 8.8.1 -- "reopening restores section and transcript" (5.3).
     */
    #[Test]
    public function reopening_a_lesson_restores_the_section_and_the_transcript(): void
    {
        $lesson = $this->lesson(2);

        $state = $this->inertiaProps(
            $this->actingAs($lesson->course->student)
                ->get(route('lessons.show', $lesson))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('lessons/show'))
        );

        $this->assertSame(2, $state['resume_at']);
        $this->assertSame(4, $state['lesson']['sections_total']);
        $this->assertSame(LessonStatus::InProgress->value, $state['lesson']['status']);

        // Two sections taught, and the Q&A turn the student had in the second.
        $this->assertSame([true, true, false, false], array_column($state['sections'], 'taught'));
        $this->assertCount(4, $state['transcript']);
        $this->assertSame('student', $state['transcript'][2]['speaker']);

        // Every section is addressable, so nothing is assembled from an id in
        // the browser.
        $this->assertSame(
            route('lessons.sections.stream', [$lesson, 2]),
            $state['sections'][1]['stream'],
        );
    }

    /**
     * 8.8.3 -- the checkpoint is on the screen, and its key is not (5.2.3).
     */
    #[Test]
    public function the_unanswered_checkpoint_arrives_stripped_and_addressable(): void
    {
        $lesson = $this->lesson(2);

        $response = $this->actingAs($lesson->course->student)
            ->get(route('lessons.show', $lesson))
            ->assertOk();

        $response->assertDontSee('correct_option_id', escape: false);
        $response->assertDontSee('«bien» is an adverb', escape: false);

        $checkpoint = collect($this->inertiaProps($response)['checkpoints'])
            ->firstWhere('section_index', 2);

        $this->assertNotNull($checkpoint);
        $this->assertArrayNotHasKey(
            'correct_option_id',
            $checkpoint['payload']['checkpoint']['questions'][0],
        );
    }

    /**
     * 8.8.4 and 8.8.5 -- "the counter reads the server's number, never a
     * duplicated constant".
     *
     * Both halves: the number reaches the screen, and the screen has no number
     * of its own to fall back on. The second is a grep because that is what
     * the failure looks like -- a literal typed into the composer that goes on
     * being right until the constant behind it moves.
     */
    #[Test]
    public function the_two_caps_are_published_and_never_restated_on_the_screen(): void
    {
        $lesson = $this->lesson(2);

        $limits = $this->inertiaProps(
            $this->actingAs($lesson->course->student)->get(route('lessons.show', $lesson))
        )['limits'];

        $this->assertSame(LessonSession::MAX_QUESTION, $limits['question_characters']);
        $this->assertSame(SelectionExplainer::MAX_SELECTION, $limits['highlight_characters']);

        $screen = (string) file_get_contents(resource_path('js/pages/lessons/show.tsx'));

        $this->assertStringNotContainsString((string) LessonSession::MAX_QUESTION, $screen);
        $this->assertStringNotContainsString((string) SelectionExplainer::MAX_SELECTION, $screen);
    }

    /**
     * 8.8.6 -- a completed lesson is a state of this screen, and the rail
     * reads the course from the same payload the column does.
     */
    #[Test]
    public function a_completed_lesson_renders_as_a_state_of_the_same_screen(): void
    {
        $lesson = $this->lesson(1);

        $state = $this->inertiaProps(
            $this->actingAs($lesson->course->student)
                ->get(route('lessons.show', $lesson))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('lessons/show'))
        );

        $this->assertSame(LessonStatus::Completed->value, $state['lesson']['status']);
        $this->assertSame(route('lessons.homework.show', $lesson), $state['urls']['homework']);

        $rail = collect($state['course']['lessons'])->keyBy('position');

        $this->assertTrue($rail[1]['completed']);
        $this->assertNotNull($rail[2]['url'], 'The lesson the student is up to must be reachable from the rail.');
        $this->assertNull($rail[3]['url'], 'A locked lesson carries no URL, which is how the rail knows not to offer it.');
    }

    /**
     * The lesson mid-flight, or the one finished before it (spec 8.2.3, 8.2.5).
     */
    private function lesson(int $position): Lesson
    {
        /** @var Course $course */
        $course = Course::query()->where('subtopic_slug', 'conversational-spanish')->sole();

        return $course->lessons()->where('position', $position)->sole();
    }
}
