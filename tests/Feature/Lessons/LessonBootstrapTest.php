<?php

namespace Tests\Feature\Lessons;

use App\Ai\LessonSystemPrompt;
use App\Ai\PromptCaching;
use App\Ai\Tier;
use App\Lessons\LessonContext;
use App\Lessons\LessonSections;
use App\Lessons\LessonSessions;
use App\Models\UsageEvent;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 5.1 -- session bootstrap: `[persona | CRITERIA | WK | GUIDANCE]` as a
 * cached prefix, transcript behind it.
 *
 * The order test is the done-condition, and it is not cosmetic. The order is
 * the cache order (ledger D5's rider, spec 2.3.3): most-stable first, so the
 * fourth lesson of a course reads a prefix the first one paid to write.
 * Reordering these is a cache invalidation dressed as a formatting change.
 */
class LessonBootstrapTest extends LessonEngineTestCase
{
    #[Test]
    public function the_system_prompt_carries_the_four_documents_in_cache_order(): void
    {
        $lesson = $this->lesson();

        $prompt = app(LessonContext::class)->systemPromptFor($lesson);

        $persona = strpos($prompt, 'You are a personal tutor working one-to-one');
        $criteria = strpos($prompt, 'Help my daughter with her homework');
        $wk = strpos($prompt, 'Reads graphs confidently');
        $guidance = strpos($prompt, '## Objective');

        foreach (['persona' => $persona, 'criteria' => $criteria, 'wk' => $wk, 'guidance' => $guidance] as $name => $position) {
            $this->assertNotFalse($position, "The {$name} document is missing from the system prompt.");
        }

        $this->assertTrue(
            $persona < $criteria && $criteria < $wk && $wk < $guidance,
            'Spec 2.3.3 and 5.1: persona, then CRITERIA, then WK, then GUIDANCE.',
        );

        // The same order, stated once, in the place the renderer reads it.
        $this->assertSame(
            ['persona', 'criteria_doc', 'wk_doc', 'guidance_doc'],
            LessonSystemPrompt::ORDER,
        );
    }

    #[Test]
    public function the_prefix_is_what_the_cache_breakpoint_is_placed_on(): void
    {
        $lesson = $this->lesson();

        $prompt = app(LessonContext::class)->systemPromptFor($lesson);

        // Spec 2.4's workaround puts an explicit breakpoint on the system
        // block. 5.1's "cache reads observed per 2.4" rests on the assembled
        // prefix being that block -- if the session sent its context any other
        // way, the breakpoint would land somewhere else.
        $options = PromptCaching::providerOptions('anthropic', $prompt);

        $this->assertSame($prompt, $options['system'][0]['text']);
        $this->assertSame(['type' => 'ephemeral'], $options['system'][0]['cache_control']);
    }

    #[Test]
    public function two_lessons_of_one_course_share_everything_but_their_guidance(): void
    {
        $course = $this->activeCourse();

        $first = app(LessonContext::class)->systemPromptFor($this->lesson($course, 1));
        $second = app(LessonContext::class)->systemPromptFor($this->lesson($course, 2));

        // The shared head is what stays warm across lessons. If the volatile
        // document came first, this prefix would be empty and every lesson
        // would pay full price.
        $shared = 0;
        $length = min(strlen($first), strlen($second));

        while ($shared < $length && $first[$shared] === $second[$shared]) {
            $shared++;
        }

        $this->assertGreaterThan(
            strpos($first, '## How this lesson runs'),
            $shared,
            'Everything up to the lesson guidance should be byte-identical between lessons.',
        );

        $this->assertStringContainsString('Letters as Numbers', $first);
        $this->assertStringContainsString('Keeping Both Sides Equal', $second);
    }

    #[Test]
    public function the_session_runs_at_the_tier_its_system_prompt_declares(): void
    {
        // `lesson.section` declares `tier: session` (2.3.2) precisely so it
        // does not choose a model of its own. What it inherits is this.
        $this->assertSame(Tier::Mid, app(LessonContext::class)->tier());
    }

    #[Test]
    public function a_streamed_section_bills_at_the_session_tier(): void
    {
        $this->scriptModel(['A variable is a letter standing in for a number.', $this->checkpointResponse()]);

        $lesson = $this->lesson();
        $frames = [];

        app(LessonSessions::class)
            ->for($lesson)
            ->teachSection(1, $this->collectingStream($frames));

        $section = UsageEvent::query()->where('prompt_key', 'lesson.section')->firstOrFail();

        $this->assertSame('lesson.section', $section->feature);
        $this->assertSame(Tier::Mid->value, $section->tier, 'Resolved from the session, not chosen by the prompt.');
        $this->assertSame($lesson->course->user_id, $section->user_id);

        // The checkpoint is a separate, cheaper call (2.3.2, 5.2.1).
        $checkpoint = UsageEvent::query()->where('prompt_key', 'checkpoint.generate')->firstOrFail();

        $this->assertSame(Tier::Cheap->value, $checkpoint->tier);
    }

    #[Test]
    public function the_sections_come_from_the_syllabus_and_are_counted_from_one(): void
    {
        $sections = LessonSections::of($this->lesson());

        $this->assertCount(2, $sections);
        $this->assertFalse($sections->has(0), 'There is no section zero.');
        $this->assertTrue($sections->has(1));
        $this->assertSame('What a variable is', $sections->title(1));
        $this->assertSame(['expression', 'coefficient'], $sections->concepts(2));
        $this->assertSame(['coefficient', 'expression', 'variable'], $sections->declaredConcepts());
        $this->assertTrue($sections->isLast(2));
    }
}
