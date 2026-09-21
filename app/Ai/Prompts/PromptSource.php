<?php

namespace App\Ai\Prompts;

/**
 * Where a prompt version's body came from (spec 15.1.1, ledger D54).
 *
 * `File` is a version `prompts:sync` read out of `prompts/` -- the reviewed
 * baseline, with a git history behind it. `Admin` is a live edit made in the
 * editor. The distinction is what keeps a deploy from overriding a live edit
 * (spec 15.1.3), so it is a column and not an inference from `author_id`.
 */
enum PromptSource: string
{
    case File = 'file';
    case Admin = 'admin';
}
