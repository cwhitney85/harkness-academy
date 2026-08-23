<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\InvalidPromptFile;
use App\Ai\Tier;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses one prompt file into a PromptDefinition (spec 2.3.1).
 *
 * Every structural rule is checked here so that a bad prompt file fails at
 * load, not at the call site: a missing frontmatter field, an unknown tier or
 * output, a key that disagrees with the file's path, or a body placeholder
 * that no `vars` entry declares.
 */
final class PromptLoader
{
    private const FRONTMATTER = '/^---\R(?<frontmatter>.*?)\R---\R(?<body>.*)$/s';

    private const PLACEHOLDER = '/\{\{\s*(?<name>[a-z0-9_]+)\s*\}\}/';

    public function load(string $path, string $expectedKey): PromptDefinition
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw InvalidPromptFile::for($path, 'the file could not be read.');
        }

        if (preg_match(self::FRONTMATTER, $contents, $matches) !== 1) {
            throw InvalidPromptFile::for($path, 'it has no YAML frontmatter block delimited by ---.');
        }

        try {
            $frontmatter = Yaml::parse($matches['frontmatter']);
        } catch (ParseException $e) {
            throw InvalidPromptFile::for($path, 'its frontmatter is not valid YAML: '.$e->getMessage());
        }

        if (! is_array($frontmatter)) {
            throw InvalidPromptFile::for($path, 'its frontmatter is not a mapping.');
        }

        $key = $this->requireString($frontmatter, 'key', $path);

        if ($key !== $expectedKey) {
            throw InvalidPromptFile::for($path, "it declares key [{$key}] but its path implies [{$expectedKey}].");
        }

        $tier = Tier::tryFrom($this->requireString($frontmatter, 'tier', $path))
            ?? throw InvalidPromptFile::for($path, 'its tier is not one of cheap, mid, top, session, none.');

        [$output, $schema] = $this->parseOutput($this->requireString($frontmatter, 'output', $path), $path);

        $vars = $this->parseVars($frontmatter, $path);

        $body = rtrim($matches['body'])."\n";

        $this->assertPlaceholdersAreDeclared($body, $vars, $path);

        return new PromptDefinition($key, $tier, $output, $schema, $vars, $body, $path);
    }

    /**
     * @return array{PromptOutput, ?string}
     */
    private function parseOutput(string $value, string $path): array
    {
        if (! str_contains($value, '/')) {
            $output = PromptOutput::tryFrom($value)
                ?? throw InvalidPromptFile::for($path, "[{$value}] is not a known output contract.");

            if ($output === PromptOutput::Json) {
                throw InvalidPromptFile::for($path, 'a json output must name a schema, e.g. json/quiz.v1.');
            }

            return [$output, null];
        }

        [$kind, $schema] = explode('/', $value, 2);

        if ($kind !== PromptOutput::Json->value || $schema === '') {
            throw InvalidPromptFile::for($path, "[{$value}] is not a known output contract.");
        }

        return [PromptOutput::Json, $schema];
    }

    /**
     * @param  array<mixed>  $frontmatter
     * @return list<string>
     */
    private function parseVars(array $frontmatter, string $path): array
    {
        $vars = $frontmatter['vars'] ?? [];

        if (! is_array($vars) || ! array_is_list($vars)) {
            throw InvalidPromptFile::for($path, 'its `vars` must be a list.');
        }

        foreach ($vars as $var) {
            if (! is_string($var) || preg_match('/^[a-z0-9_]+$/', $var) !== 1) {
                throw InvalidPromptFile::for($path, 'its `vars` entries must be snake_case strings.');
            }
        }

        if (count(array_unique($vars)) !== count($vars)) {
            throw InvalidPromptFile::for($path, 'its `vars` list repeats a name.');
        }

        return $vars;
    }

    /**
     * @param  list<string>  $vars
     */
    private function assertPlaceholdersAreDeclared(string $body, array $vars, string $path): void
    {
        preg_match_all(self::PLACEHOLDER, $body, $matches);

        $used = array_values(array_unique($matches['name']));
        $undeclared = array_diff($used, $vars);

        if ($undeclared !== []) {
            throw InvalidPromptFile::for(
                $path,
                'its body uses ['.implode(', ', $undeclared).'] which `vars` does not declare.'
            );
        }
    }

    /**
     * @param  array<mixed>  $frontmatter
     */
    private function requireString(array $frontmatter, string $field, string $path): string
    {
        $value = $frontmatter[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw InvalidPromptFile::for($path, "its frontmatter has no `{$field}`.");
        }

        return $value;
    }
}
