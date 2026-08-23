<?php

namespace App\Ai\Prompts;

/**
 * What a prompt is contracted to return (spec 2.3.1 frontmatter `output`).
 *
 * `Fragment` and `System` never reach a provider on their own -- they compose
 * into another prompt (spec 2.3.3). `Json` carries a schema name in the raw
 * frontmatter value, e.g. `json/syllabus.v1`.
 */
enum PromptOutput: string
{
    case Fragment = 'fragment';
    case System = 'system';
    case Stream = 'stream';
    case Markdown = 'markdown';
    case Json = 'json';

    /**
     * Whether a prompt with this output is ever sent to a provider by itself.
     */
    public function isCallable(): bool
    {
        return ! in_array($this, [self::Fragment, self::System], true);
    }
}
