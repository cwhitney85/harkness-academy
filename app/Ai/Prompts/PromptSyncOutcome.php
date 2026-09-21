<?php

namespace App\Ai\Prompts;

/**
 * What one `prompts:sync` did to one prompt (spec 15.1.2, ledger D65).
 */
enum PromptSyncOutcome: string
{
    /** The prompt had no version, so the file's body became version 1. */
    case Seeded = 'seeded';

    /** The contract was written; the body is the editor's and was left alone. */
    case Registered = 'registered';
}
