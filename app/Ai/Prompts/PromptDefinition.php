<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\PromptVariableMismatch;
use App\Ai\Tier;

/**
 * A prompt's contract plus the template body that runs (spec 2.3.1).
 *
 * The contract -- `output` and `vars` -- comes from the file; the body and the
 * tier come from whichever version is active (spec 15.1.1, ledger D54). Both
 * halves of the registry serve this same shape, so a call site cannot tell
 * whether it is holding a file or a live edit.
 *
 * Invariant, enforced where a definition is built: every `{{ placeholder }}`
 * in the body is declared in `vars`. A definition that reaches this class is
 * already internally consistent, so the only way `render()` can fail is a
 * caller that passes the wrong variables.
 */
final readonly class PromptDefinition
{
    /**
     * @param  list<string>  $vars  Declared variables, in frontmatter order.
     */
    public function __construct(
        public string $key,
        public Tier $tier,
        public PromptOutput $output,
        public ?string $schema,
        public array $vars,
        public string $body,
    ) {}

    /**
     * The variable names a body interpolates.
     *
     * The one reader of the `{{ name }}` syntax: the loader holds a file's
     * body to its own `vars` with it (spec 2.3.1), and `prompts:sync` holds a
     * live edit to the file's (spec 15.1.4).
     *
     * @return list<string>
     */
    public static function placeholders(string $body): array
    {
        preg_match_all('/\{\{\s*(?<name>[a-z0-9_]+)\s*\}\}/', $body, $matches);

        return array_values(array_unique($matches['name']));
    }

    /**
     * Render the template.
     *
     * @param  array<string, string|\Stringable>  $values  Must supply exactly the declared vars.
     *
     * @throws PromptVariableMismatch
     */
    public function render(array $values = []): string
    {
        $supplied = array_keys($values);

        $missing = array_values(array_diff($this->vars, $supplied));
        $undeclared = array_values(array_diff($supplied, $this->vars));

        if ($missing !== [] || $undeclared !== []) {
            throw PromptVariableMismatch::for($this->key, $missing, $undeclared);
        }

        $replacements = [];

        foreach ($values as $name => $value) {
            $replacements['{{ '.$name.' }}'] = (string) $value;
        }

        return strtr($this->body, $replacements);
    }

    /**
     * The raw frontmatter `output` value, e.g. `markdown` or `json/quiz.v1`.
     */
    public function outputContract(): string
    {
        return $this->schema === null
            ? $this->output->value
            : $this->output->value.'/'.$this->schema;
    }
}
