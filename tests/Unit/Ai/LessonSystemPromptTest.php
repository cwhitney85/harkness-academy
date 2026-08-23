<?php

namespace Tests\Unit\Ai;

use App\Ai\Gateway;
use App\Ai\LessonSystemPrompt;
use App\Ai\Prompts\PromptRepository;
use App\Ai\UsageRecorder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.3.3 -- composition and cache order for `lesson.system`.
 */
class LessonSystemPromptTest extends TestCase
{
    #[Test]
    public function it_composes_persona_criteria_wk_and_guidance_in_that_order(): void
    {
        $rendered = $this->composer()->render(
            persona: ['student_name' => 'Ada', 'subtopic' => 'Intro', 'skill_level' => '101'],
            criteriaDoc: 'CRITERIA-MARKER',
            wkDoc: 'WK-MARKER',
            guidanceDoc: 'GUIDANCE-MARKER',
        );

        $positions = [
            'persona' => strpos($rendered, 'Ada'),
            'criteria' => strpos($rendered, 'CRITERIA-MARKER'),
            'wk' => strpos($rendered, 'WK-MARKER'),
            'guidance' => strpos($rendered, 'GUIDANCE-MARKER'),
        ];

        foreach ($positions as $part => $position) {
            $this->assertNotFalse($position, "The [{$part}] section is missing from lesson.system.");
        }

        $ordered = $positions;
        asort($ordered);

        $this->assertSame(
            ['persona', 'criteria', 'wk', 'guidance'],
            array_keys($ordered),
            'lesson.system must be [persona | criteria | wk | guidance], most-stable-first -- this order is the cached prefix of spec 5.1.',
        );
    }

    #[Test]
    public function the_declared_order_matches_the_order_the_template_actually_renders(): void
    {
        $body = (new PromptRepository(base_path('prompts')))->get(LessonSystemPrompt::KEY)->body;

        $positions = [];

        foreach (LessonSystemPrompt::ORDER as $var) {
            $position = strpos($body, '{{ '.$var.' }}');

            $this->assertNotFalse($position, "lesson.system does not interpolate [{$var}].");

            $positions[$var] = $position;
        }

        $sorted = $positions;
        asort($sorted);

        $this->assertSame(LessonSystemPrompt::ORDER, array_keys($sorted));
    }

    #[Test]
    public function persona_prepends_only_into_lesson_system(): void
    {
        $repository = new PromptRepository(base_path('prompts'));

        foreach ($repository->all() as $key => $prompt) {
            if ($key === LessonSystemPrompt::KEY) {
                $this->assertContains('persona', $prompt->vars);

                continue;
            }

            $this->assertNotContains(
                'persona',
                $prompt->vars,
                "Prompt [{$key}] takes a persona; spec 2.3.3 says persona composes into lesson.system only.",
            );
        }
    }

    #[Test]
    public function editing_the_persona_leaves_every_other_prompt_byte_identical(): void
    {
        $repository = new PromptRepository(base_path('prompts'));

        $before = $this->renderAllUnrelatedToPersona($repository);
        $lessonSystemBefore = $this->composer()->render($this->persona(), '[criteria_doc]', '[wk_doc]', '[guidance_doc]');

        $personaPath = base_path('prompts/persona/default.md');
        $original = (string) file_get_contents($personaPath);

        try {
            file_put_contents($personaPath, $original."\nYou also speak entirely in limericks.\n");

            $after = $this->renderAllUnrelatedToPersona(new PromptRepository(base_path('prompts')));
            $lessonSystemAfter = $this->composer()->render($this->persona(), '[criteria_doc]', '[wk_doc]', '[guidance_doc]');
        } finally {
            file_put_contents($personaPath, $original);
        }

        $this->assertSame($before, $after, 'A persona edit must not change the bytes of any other prompt.');

        $this->assertNotSame(
            $lessonSystemBefore,
            $lessonSystemAfter,
            'lesson.system is the one prompt a persona edit is supposed to change; if it does not, the composition is not wired up.',
        );
    }

    /**
     * @return array<string, string>
     */
    private function renderAllUnrelatedToPersona(PromptRepository $repository): array
    {
        $rendered = [];

        foreach ($repository->all() as $key => $prompt) {
            // The persona itself and the one prompt it composes into are the
            // two an edit is allowed to move; everything else must not budge.
            if (in_array($key, [LessonSystemPrompt::KEY, LessonSystemPrompt::PERSONA_KEY], true)) {
                continue;
            }

            $values = [];

            foreach ($prompt->vars as $var) {
                $values[$var] = '['.$var.']';
            }

            $rendered[$key] = $prompt->render($values);
        }

        return $rendered;
    }

    /**
     * @return array{student_name: string, subtopic: string, skill_level: string}
     */
    private function persona(): array
    {
        return ['student_name' => 'Ada', 'subtopic' => 'Intro', 'skill_level' => '101'];
    }

    private function composer(): LessonSystemPrompt
    {
        return new LessonSystemPrompt(
            new Gateway(new PromptRepository(base_path('prompts')), new UsageRecorder),
        );
    }
}
