<?php

namespace Tests\Unit\Ai;

use App\Ai\Prompts\PromptFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.3.5 -- golden tests for template rendering, one per inventory prompt.
 *
 * Only the rendering is golden. There are deliberately no model-output
 * goldens: those are flaky by nature and the spec says so. What this locks is
 * the bytes we send, so an edit to a prompt has to be an edit to a fixture too
 * -- which is what makes a prompt change visible in review.
 *
 * Regenerate after an intentional prompt edit with:
 *   UPDATE_PROMPT_FIXTURES=1 vendor/bin/phpunit tests/Unit/Ai/PromptRenderFixturesTest.php
 */
class PromptRenderFixturesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function promptKeys(): iterable
    {
        foreach ((new PromptFiles(dirname(__DIR__, 3).'/prompts'))->keys() as $key) {
            yield $key => [$key];
        }
    }

    #[Test]
    #[DataProvider('promptKeys')]
    public function it_renders_to_its_fixture(string $key): void
    {
        $prompt = (new PromptFiles(base_path('prompts')))->get($key);

        $rendered = $prompt->render(self::sampleVars($prompt->vars));

        $fixture = base_path('tests/fixtures/prompts/'.str_replace('.', '/', $key).'.txt');

        if (getenv('UPDATE_PROMPT_FIXTURES')) {
            if (! is_dir(dirname($fixture))) {
                mkdir(dirname($fixture), 0o755, true);
            }

            file_put_contents($fixture, $rendered);
        }

        $this->assertFileExists($fixture, "Prompt [{$key}] has no render fixture (spec 2.3.5 wants 1:1 coverage).");
        $this->assertSame(file_get_contents($fixture), $rendered, "Prompt [{$key}] no longer renders to its fixture.");
    }

    #[Test]
    public function every_prompt_has_a_fixture_and_every_fixture_has_a_prompt(): void
    {
        $keys = (new PromptFiles(base_path('prompts')))->keys();

        $fixtures = array_map(
            fn (string $path): string => str_replace(
                '/', '.', substr($path, strlen(base_path('tests/fixtures/prompts/')), -4)
            ),
            glob(base_path('tests/fixtures/prompts/*/*.txt')) ?: [],
        );

        sort($fixtures);

        $this->assertSame($keys, $fixtures, 'Spec 2.3.5 wants exactly one render fixture per inventory prompt.');
    }

    /**
     * Deterministic, visibly-substituted values -- a fixture should make it
     * obvious where each variable landed.
     *
     * @param  list<string>  $vars
     * @return array<string, string>
     */
    private static function sampleVars(array $vars): array
    {
        $values = [];

        foreach ($vars as $var) {
            $values[$var] = '['.$var.']';
        }

        return $values;
    }
}
