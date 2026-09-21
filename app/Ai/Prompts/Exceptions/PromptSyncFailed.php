<?php

namespace App\Ai\Prompts\Exceptions;

/**
 * A `prompts:sync` the registry refused (spec 15.1.4).
 *
 * The contract runs one way: the editor owns the body, the file owns `vars`.
 * A file that drops a variable the running body interpolates would leave that
 * body rendering the placeholder verbatim on the next call, so sync stops
 * before anything is written and names both sides of the collision.
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
            "Prompt file [{$path}] no longer declares [".implode(', ', $vars).'], which the active '
            ."version (v{$version}) of [{$key}] interpolates. Edit that prompt in Admin to stop using them, or "
            .'put them back in the file; nothing was synced.'
        );
    }
}
