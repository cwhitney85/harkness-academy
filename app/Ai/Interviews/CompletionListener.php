<?php

namespace App\Ai\Interviews;

/**
 * Receives the one CompletionSignal an interview produces (spec 2.3.4).
 *
 * Package 4 implements this with the summarization workflow. Package 2 owns
 * only the contract and the two paths that satisfy it.
 */
interface CompletionListener
{
    public function completed(CompletionSignal $signal): void;
}
