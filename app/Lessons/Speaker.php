<?php

namespace App\Lessons;

use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;

/**
 * Who said a line of a lesson (spec 5.2.6).
 *
 * A deliberate twin of `App\Courses\Interviews\Speaker` rather than a shared
 * enum. Ledger D9 split the two transcripts apart because their retention
 * rules are opposite -- interview turns are garbage the moment they are
 * summarized, lesson turns are what 5.3 resumes from -- and a shared enum
 * would re-couple the packages at exactly that seam. The twelve duplicated
 * lines are the price of that separation, and they encode a fact about the
 * provider (student speaks as the user, tutor as the assistant) rather than
 * anything either package can change unilaterally.
 */
enum Speaker: string
{
    case Student = 'student';

    case Tutor = 'tutor';

    /**
     * The label in the transcript handed to `lesson.summarize` (spec 5.4.2).
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Tutor => 'Tutor',
        };
    }

    public function message(string $content): Message
    {
        return match ($this) {
            self::Student => new UserMessage($content),
            self::Tutor => new AssistantMessage($content),
        };
    }
}
