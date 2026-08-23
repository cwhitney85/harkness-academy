<?php

namespace App\Ai\Interviews;

/**
 * An interview has ended (spec 2.3.4).
 *
 * Both termination paths -- the model's `signal_complete` tool call and the
 * student's Done button -- produce this same value, so the summarization
 * workflow downstream has exactly one shape to handle and cannot behave
 * differently depending on who stopped the conversation.
 */
final readonly class CompletionSignal
{
    private function __construct(
        public CompletionSource $source,
        public string $reason,
    ) {}

    public static function fromModel(string $reason): self
    {
        return new self(CompletionSource::Model, trim($reason));
    }

    /**
     * The Done button bypasses the model and lands on the same signal.
     */
    public static function fromStudent(): self
    {
        return new self(CompletionSource::Student, 'The student ended the interview.');
    }
}
