<?php

namespace Tests\Feature\Lessons;

use App\Ai\Tier;
use App\Courses\LessonStatus;
use App\Lessons\SelectionExplainer;
use App\Lessons\Speaker;
use App\Lessons\Streaming\TurnKind;
use App\Models\LessonMessage;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.5 -- the select-mode side-tab.
 *
 * The done-condition is a negative one: the lesson transcript is byte-identical
 * before and after the side-tab is used. So the first test here reads every
 * column of every row twice and compares, rather than counting rows -- a
 * feature that quietly rewrote a message would pass a count.
 *
 * The rest pin the boundary the statelessness rests on. The client says *which*
 * tutor turn it highlighted; the server supplies the passage. Nothing else in
 * the call is client-authored except the highlight itself.
 */
class SelectionExplainerTest extends SectionStreamTestCase
{
    /**
     * A taught section long enough that a window around the highlight is a
     * genuinely smaller thing than the section. Without that, every assertion
     * about narrowing would pass whether the narrowing happened or not.
     */
    private const OPENING = 'This section opens with a shopping list nobody has counted yet.';

    private const TARGET = 'a letter standing in for a number you have not pinned down yet';

    private const CLOSING = 'And that, restated at the very end, is the whole idea of a variable.';

    #[Test]
    public function the_transcript_is_byte_identical_before_and_after(): void
    {
        $this->taughtSection();

        $before = $this->transcriptSnapshot();

        $this->scriptModel(['A letter used this way is called a variable.']);

        $this->explain(self::TARGET)
            ->assertOk()
            ->assertJson(['explanation' => 'A letter used this way is called a variable.']);

        $this->assertSame($before, $this->transcriptSnapshot());
    }

    #[Test]
    public function it_bills_one_cheap_tier_row_of_its_own(): void
    {
        $this->taughtSection();

        $billedForTeaching = UsageEvent::query()->count();

        $this->scriptModel(['A letter used this way is called a variable.']);

        $this->explain(self::TARGET)->assertOk();

        $this->assertSame($billedForTeaching + 1, UsageEvent::query()->count());

        $event = UsageEvent::query()->where('prompt_key', 'explainer.select')->firstOrFail();

        $this->assertSame('lesson.explainer', $event->feature);
        $this->assertSame(Tier::Cheap->value, $event->tier, 'The side-tab must never ride the session tier.');
        $this->assertSame($this->lesson->course->user_id, $event->user_id);
    }

    #[Test]
    public function the_call_carries_the_highlight_and_the_passage_around_it(): void
    {
        $this->taughtSection();

        $this->scriptModel(['A letter used this way is called a variable.']);

        $this->explain(self::TARGET)->assertOk();

        $sent = $this->sent[0];

        $this->assertStringContainsString(self::TARGET, $sent);
        $this->assertStringContainsString($this->lesson->course->subtopic_title, $sent);
        $this->assertStringContainsString(
            $this->lesson->course->skill_level->pathSegment(),
            $sent,
            'Depth is part of the ask, the same way it is in the lesson (4.5.6).',
        );

        // Narrowed, and demonstrably so: both ends of the section sit further
        // from the highlight than the radius, and neither travels.
        $this->assertStringNotContainsString(self::OPENING, $sent);
        $this->assertStringNotContainsString(self::CLOSING, $sent);
    }

    #[Test]
    public function a_highlight_the_source_does_not_contain_verbatim_widens_to_the_whole_passage(): void
    {
        $this->taughtSection();

        $this->scriptModel(['A letter used this way is called a variable.']);

        // What a client sends after rendering markdown: emphasis marks gone,
        // so the phrase never appears in the stored source exactly as quoted.
        $this->explain('is **a letter** standing in for a number')->assertOk();

        $this->assertStringContainsString(
            self::OPENING,
            $this->sent[0],
            'A miss widens rather than guessing a window, so the model always has real context.',
        );
        $this->assertStringContainsString(self::CLOSING, $this->sent[0]);
    }

    #[Test]
    public function the_side_tab_still_works_on_a_finished_lesson(): void
    {
        $this->taughtSection();

        $this->lesson->forceFill(['status' => LessonStatus::Completed, 'completed_at' => now()])->save();

        $this->scriptModel(['A letter used this way is called a variable.']);

        // A stateless read cannot violate a progress invariant, and revision is
        // exactly when a student most wants to ask what a phrase meant.
        $this->explain(self::TARGET)->assertOk();
    }

    #[Test]
    public function a_passage_from_another_lesson_is_refused(): void
    {
        $this->taughtSection();

        $elsewhere = LessonMessage::query()->firstOrFail();
        $other = $this->lesson($this->lesson->course, position: 2);

        $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$other->id}/explanations", [
                'message_id' => $elsewhere->id,
                'selection' => self::TARGET,
            ])
            ->assertStatus(422);

        $this->assertSame(0, UsageEvent::query()->where('prompt_key', 'explainer.select')->count());
    }

    #[Test]
    public function the_students_own_words_are_not_a_passage(): void
    {
        $this->taughtSection();

        $asked = LessonMessage::query()->create([
            'lesson_id' => $this->lesson->id,
            'turn_id' => (string) Str::uuid(),
            'kind' => TurnKind::Qa,
            'speaker' => Speaker::Student,
            'section_index' => 1,
            'content' => str_repeat('Anything at all. ', 100),
            'position' => 99,
            'created_at' => now(),
        ]);

        // The passage is always tutor text, so the only client-authored bytes
        // in the call stay capped at MAX_SELECTION.
        $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$this->lesson->id}/explanations", [
                'message_id' => $asked->id,
                'selection' => 'Anything at all.',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function a_highlight_longer_than_the_cap_is_refused_in_words_a_student_can_act_on(): void
    {
        $this->taughtSection();

        $response = $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$this->lesson->id}/explanations", [
                'message_id' => LessonMessage::query()->firstOrFail()->id,
                'selection' => str_repeat('x', SelectionExplainer::MAX_SELECTION + 1),
            ])
            ->assertStatus(422);

        // The client renders this verbatim, so it says what to do next rather
        // than naming a form field and a number.
        $message = $response->json('errors.selection.0');

        $this->assertStringContainsString('shorter passage', (string) $message);
        $this->assertStringNotContainsString('field', (string) $message);

        $this->assertSame(0, UsageEvent::query()->where('prompt_key', 'explainer.select')->count());
    }

    #[Test]
    public function an_over_cap_highlight_is_counted_so_the_cap_can_be_revisited(): void
    {
        $this->taughtSection();

        Log::spy();

        $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$this->lesson->id}/explanations", [
                'message_id' => LessonMessage::query()->firstOrFail()->id,
                'selection' => str_repeat('x', SelectionExplainer::MAX_SELECTION + 1),
            ])
            ->assertStatus(422);

        // D16 says 500 is a chosen number whose revisit trigger is students
        // hitting it. A 422 nobody counts makes that trigger unactionable.
        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'longer than the cap')
                    && $context['characters'] === SelectionExplainer::MAX_SELECTION + 1
                    && $context['cap'] === SelectionExplainer::MAX_SELECTION
                    && $context['user_id'] === $this->lesson->course->user_id;
            })
            ->once();
    }

    #[Test]
    public function a_highlight_at_the_cap_is_not_counted(): void
    {
        $this->taughtSection();

        Log::spy();

        $this->scriptModel(['A letter used this way is called a variable.']);

        $this->explain(str_repeat('x', SelectionExplainer::MAX_SELECTION))->assertOk();

        Log::shouldNotHaveReceived('info');
    }

    #[Test]
    public function the_published_highlight_limit_is_the_one_actually_enforced(): void
    {
        $this->taughtSection();

        $cap = $this->actingAs($this->lesson->course->student)
            ->getJson("/lessons/{$this->lesson->id}")
            ->json('limits.highlight_characters');

        $this->assertIsInt($cap);

        $this->scriptModel(['A letter used this way is called a variable.']);

        // The number the screen draws its counter from has to be the number the
        // server refuses at, or the screen is confidently wrong.
        $this->explain(str_repeat('x', $cap))->assertOk();
        $this->explain(str_repeat('x', $cap + 1))->assertStatus(422);
    }

    #[Test]
    public function another_student_cannot_use_the_side_tab(): void
    {
        $this->taughtSection();

        $this->actingAs(User::factory()->create())
            ->postJson("/lessons/{$this->lesson->id}/explanations", [
                'message_id' => LessonMessage::query()->firstOrFail()->id,
                'selection' => self::TARGET,
            ])
            ->assertForbidden();
    }

    /**
     * Opening, a long stretch of prose, the highlighted phrase, another long
     * stretch, closing -- so the two ends fall outside a window taken around
     * the phrase.
     */
    private static function section(): string
    {
        $filler = trim(str_repeat('You can count them later once you decide what to call them. ', 14));

        return self::OPENING." {$filler} A variable is ".self::TARGET.". {$filler} ".self::CLOSING;
    }

    /**
     * Teach section 1, so there is a real tutor turn to highlight.
     */
    private function taughtSection(): void
    {
        $this->scriptModel([self::section(), $this->checkpointResponse()]);

        $frames = [];
        $this->teach(1, $frames);
    }

    private function explain(string $selection): TestResponse
    {
        return $this->actingAs($this->lesson->course->student)
            ->postJson("/lessons/{$this->lesson->id}/explanations", [
                'message_id' => LessonMessage::query()->where('speaker', Speaker::Tutor->value)->firstOrFail()->id,
                'selection' => $selection,
            ]);
    }

    /**
     * Every column of every transcript row, as bytes.
     */
    private function transcriptSnapshot(): string
    {
        return json_encode(
            DB::table('lesson_messages')->orderBy('id')->get()->all(),
            JSON_THROW_ON_ERROR,
        );
    }
}
