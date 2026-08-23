<?php

namespace App\Ai\Interviews;

/**
 * The student's Done button (spec 2.3.4, decision R3).
 *
 * The always-visible button bypasses the model entirely and fires the same
 * listener the `signal_complete` tool fires. Keeping it a named class rather
 * than an inline controller call is what makes "both paths, one workflow"
 * testable instead of aspirational.
 */
final class DoneButton
{
    public function __construct(private readonly CompletionListener $listener) {}

    public function press(): CompletionSignal
    {
        $signal = CompletionSignal::fromStudent();

        $this->listener->completed($signal);

        return $signal;
    }
}
