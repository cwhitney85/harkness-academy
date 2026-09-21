<?php

namespace App\Ai\Prompts\Exceptions;

/**
 * A `prompts:sync` the registry refused (spec 15.1.4).
 *
 * The contract runs one way: a live edit may change the body, but the file
 * owns `vars`, so a file that drops a variable the running body interpolates
 * would leave that body rendering a hole on the next call. Sync stops before
 * anything is written and names both sides of the collision.
 */
class PromptSyncFailed extends PromptException
{
    /**
     * @param  list<string>  $vars
     */
    public static function fileDropsVarsInUse(string $key, string $path, int $version, array $vars): self
    {
        sort($vars);

        return new self(
            "Prompt file [{$path}] no longer declares [".implode(', ', $vars).'], which the active admin '
            ."version (v{$version}) of [{$key}] interpolates. Pull that version back into the file, or activate a "
            .'file version first; nothing was synced.'
        );
    }
}
