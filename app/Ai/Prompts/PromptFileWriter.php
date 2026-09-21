<?php

namespace App\Ai\Prompts;

use App\Models\Prompt;

/**
 * Renders a prompt back into the file format `PromptLoader` reads
 * (spec 15.2.5).
 *
 * Invariant: `PromptLoader::load()` on what this writes gives back the
 * definition it was handed -- same body, same tier, same contract. The
 * frontmatter order is the inventory's own, so a prompt nobody has edited
 * rewrites to the bytes already in git.
 */
final class PromptFileWriter
{
    /**
     * Pre:  the prompt has an active version.
     * Post: the returned text parses back to that version.
     */
    public static function render(Prompt $prompt): string
    {
        $active = $prompt->activeVersion;

        $frontmatter = [
            'key: '.$prompt->key,
            'tier: '.$active->tier->value,
            'output: '.$prompt->output,
        ];

        // Omitted rather than written empty: the loader defaults a missing
        // `vars` to none, and `vars:` alone parses as null and is refused.
        if ($prompt->vars !== []) {
            $frontmatter[] = 'vars:';

            foreach ($prompt->vars as $var) {
                $frontmatter[] = '  - '.$var;
            }
        }

        return "---\n".implode("\n", $frontmatter)."\n---\n".rtrim($active->body)."\n";
    }
}
