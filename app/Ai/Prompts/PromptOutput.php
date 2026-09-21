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
     * Split a raw frontmatter contract -- `markdown`, `json/quiz.v1` -- into
     * its output and its schema name.
     *
     * Null where the string names no contract this app knows, so the caller
     * can blame whoever handed it over: a prompt file (spec 2.3.1) or a
     * `prompts` row (spec 15.1.1).
     *
     * @return array{self, ?string}|null
     */
    public static function parse(string $contract): ?array
    {
        if (! str_contains($contract, '/')) {
            $output = self::tryFrom($contract);

            return $output === null || $output === self::Json ? null : [$output, null];
        }

        [$kind, $schema] = explode('/', $contract, 2);

        return $kind === self::Json->value && $schema !== '' ? [self::Json, $schema] : null;
    }

    /**
     * Whether a prompt with this output is ever sent to a provider by itself.
     */
    public function isCallable(): bool
    {
        return ! in_array($this, [self::Fragment, self::System], true);
    }
}
