<?php

namespace App\Courses\Interviews;

use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;

/**
 * Who said a line of an interview (spec 4.3).
 *
 * The transcript is read twice, by two different readers: the provider, which
 * wants user/assistant messages, and the summarizing prompt, which wants a
 * script a person could read. Both renderings start here so they cannot
 * disagree about who was speaking.
 */
enum Speaker: string
{
    case Student = 'student';

    case Tutor = 'tutor';

    /**
     * The label in the transcript handed to `wk.create` / `criteria.create`.
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
