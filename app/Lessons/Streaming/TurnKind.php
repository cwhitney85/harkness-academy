<?php

namespace App\Lessons\Streaming;

/**
 * What kind of turn is streaming (spec 5.2.5).
 *
 * One vocabulary for every streamed turn in the product, which is the point of
 * 5.2.5: a client that can render a lesson section can render an interview
 * question without learning a second event language.
 *
 * Only `Section` and `Qa` become `lesson_messages` rows. `Interview` turns
 * live in `interview_messages` (ledger D9) and `Explainer` turns are stateless
 * by contract -- 5.5 requires the lesson transcript to be byte-identical
 * before and after the side-tab is used.
 */
enum TurnKind: string
{
    case Section = 'section';
    case Qa = 'qa';
    case Interview = 'interview';
    case Explainer = 'explainer';

    /**
     * Whether a turn of this kind is written to the lesson transcript.
     */
    public function isTranscribed(): bool
    {
        return $this === self::Section || $this === self::Qa;
    }
}
