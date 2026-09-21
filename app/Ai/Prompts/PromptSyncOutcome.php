<?php

namespace App\Ai\Prompts;

/**
 * What one `prompts:sync` did to one prompt (spec 15.1.2, 15.1.3).
 */
enum PromptSyncOutcome: string
{
    /** The file has not changed since the version last made from it. */
    case Unchanged = 'unchanged';

    /** The file made a new version, and that version is what now runs. */
    case Activated = 'activated';

    /** The file made a new version, which waits behind a live edit. */
    case Waiting = 'waiting';
}
