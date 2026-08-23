<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\PromptVariableMismatch;
use App\Ai\Tier;

/**
 * One prompt file in `prompts/` -- its frontmatter contract plus its template
 * body (spec 2.3.1).
 *
 * Invariant, enforced at load by the loader: every `{{ placeholder }}` in the
 * body is declared in `vars`. A definition that reaches this class is already
 * internally consistent, so the only way `render()` can fail is a caller that
 * passes the wrong variables.
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
        public string $path,
    ) {}

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
