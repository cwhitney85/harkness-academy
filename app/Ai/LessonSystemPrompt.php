<?php

namespace App\Ai;

/**
 * Composition and cache order for `lesson.system` (spec 2.3.3).
 *
 * The order is [persona | criteria | wk | guidance] -- most stable first --
 * because it is also the cached prefix of the lesson session (spec 5.1, and
 * the `system` breakpoint in PromptCaching). Reordering these is a cache
 * invalidation, not a formatting change.
 *
 * `persona.default` prepends here and nowhere else: no other prompt in the
 * 2.3.2 inventory composes it.
 */
final class LessonSystemPrompt
{
    /** @var list<string> */
    public const ORDER = ['persona', 'criteria_doc', 'wk_doc', 'guidance_doc'];

    public const PERSONA_KEY = 'persona.default';

    public const KEY = 'lesson.system';

    public function __construct(private readonly Gateway $gateway) {}

    /**
     * Render the system prompt for one lesson session.
     *
     * @param  array{student_name: string, subtopic: string, skill_level: string}  $persona
     */
    public function render(array $persona, string $criteriaDoc, string $wkDoc, string $guidanceDoc): string
    {
        return $this->gateway->render(self::KEY, [
            'persona' => $this->gateway->render(self::PERSONA_KEY, $persona),
            'criteria_doc' => $criteriaDoc,
            'wk_doc' => $wkDoc,
            'guidance_doc' => $guidanceDoc,
        ]);
    }
}
