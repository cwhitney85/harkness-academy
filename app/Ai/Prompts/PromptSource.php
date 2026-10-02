<?php

namespace App\Ai\Prompts;

/**
 * Where a prompt version's body came from (spec 15.1.1, ledger D65).
 *
 * `File` is the version `prompts:sync` seeded out of `prompts/`, so there is
 * at most one per prompt; `Admin` is an editor save. Nothing branches on it --
 * the editor owns every body after the first, and sync reconciles nothing
 * (15.1.3) -- but the editor shows it, and a version whose text was never in
 * git is worth being able to see at a glance.
 */
enum PromptSource: string
{
    case File = 'file';
    case Admin = 'admin';
}
