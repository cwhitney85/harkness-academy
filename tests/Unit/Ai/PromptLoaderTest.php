<?php

namespace Tests\Unit\Ai;

use App\Ai\Prompts\Exceptions\InvalidPromptFile;
use App\Ai\Prompts\Exceptions\PromptVariableMismatch;
use App\Ai\Prompts\Exceptions\UnknownPromptKey;
use App\Ai\Prompts\PromptLoader;
use App\Ai\Prompts\PromptOutput;
use App\Ai\Prompts\PromptRepository;
use App\Ai\Tier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.3.1 -- prompt file conventions and the render contract.
 */
class PromptLoaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/opentutor-prompts-'.bin2hex(random_bytes(6));

        mkdir($this->directory, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*/*.md') ?: [] as $file) {
            unlink($file);
        }

        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            rmdir($dir);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_parses_frontmatter_and_body(): void
    {
        $path = $this->write('demo/basic', <<<'MD'
            ---
            key: demo.basic
            tier: mid
            output: markdown
            vars:
              - subject
            ---
            Write about {{ subject }}.
            MD);

        $prompt = (new PromptLoader)->load($path, 'demo.basic');

        $this->assertSame('demo.basic', $prompt->key);
        $this->assertSame(Tier::Mid, $prompt->tier);
        $this->assertSame(PromptOutput::Markdown, $prompt->output);
        $this->assertNull($prompt->schema);
        $this->assertSame(['subject'], $prompt->vars);
        $this->assertSame("Write about {{ subject }}.\n", $prompt->body);
    }

    #[Test]
    public function it_parses_a_json_output_schema(): void
    {
        $prompt = (new PromptLoader)->load($this->write('demo/structured', <<<'MD'
            ---
            key: demo.structured
            tier: top
            output: json/syllabus.v1
            ---
            Build it.
            MD), 'demo.structured');

        $this->assertSame(PromptOutput::Json, $prompt->output);
        $this->assertSame('syllabus.v1', $prompt->schema);
        $this->assertSame('json/syllabus.v1', $prompt->outputContract());
    }

    #[Test]
    public function rendering_fails_on_a_missing_var(): void
    {
        $prompt = (new PromptLoader)->load($this->write('demo/two', <<<'MD'
            ---
            key: demo.two
            tier: mid
            output: markdown
            vars:
              - one
              - two
            ---
            {{ one }} and {{ two }}.
            MD), 'demo.two');

        $this->expectException(PromptVariableMismatch::class);
        $this->expectExceptionMessage('missing [two]');

        $prompt->render(['one' => 'a']);
    }

    #[Test]
    public function rendering_fails_on_an_undeclared_var(): void
    {
        $prompt = (new PromptLoader)->load($this->write('demo/one', <<<'MD'
            ---
            key: demo.one
            tier: mid
            output: markdown
            vars:
              - one
            ---
            {{ one }}.
            MD), 'demo.one');

        $this->expectException(PromptVariableMismatch::class);
        $this->expectExceptionMessage('undeclared [nope]');

        $prompt->render(['one' => 'a', 'nope' => 'b']);
    }

    #[Test]
    public function a_body_placeholder_that_vars_does_not_declare_fails_at_load(): void
    {
        $path = $this->write('demo/leaky', <<<'MD'
            ---
            key: demo.leaky
            tier: mid
            output: markdown
            vars:
              - declared
            ---
            {{ declared }} and {{ smuggled }}.
            MD);

        $this->expectException(InvalidPromptFile::class);
        $this->expectExceptionMessage('[smuggled]');

        (new PromptLoader)->load($path, 'demo.leaky');
    }

    #[Test]
    public function a_key_that_disagrees_with_its_path_fails_at_load(): void
    {
        $path = $this->write('demo/mislabelled', <<<'MD'
            ---
            key: demo.something_else
            tier: mid
            output: markdown
            ---
            Body.
            MD);

        $this->expectException(InvalidPromptFile::class);

        (new PromptLoader)->load($path, 'demo.mislabelled');
    }

    #[Test]
    public function an_unknown_tier_fails_at_load(): void
    {
        $path = $this->write('demo/badtier', <<<'MD'
            ---
            key: demo.badtier
            tier: luxury
            output: markdown
            ---
            Body.
            MD);

        $this->expectException(InvalidPromptFile::class);

        (new PromptLoader)->load($path, 'demo.badtier');
    }

    #[Test]
    public function a_json_output_without_a_schema_fails_at_load(): void
    {
        $path = $this->write('demo/schemaless', <<<'MD'
            ---
            key: demo.schemaless
            tier: top
            output: json
            ---
            Body.
            MD);

        $this->expectException(InvalidPromptFile::class);
        $this->expectExceptionMessage('must name a schema');

        (new PromptLoader)->load($path, 'demo.schemaless');
    }

    #[Test]
    public function an_unknown_key_throws(): void
    {
        $this->write('demo/basic', <<<'MD'
            ---
            key: demo.basic
            tier: mid
            output: markdown
            ---
            Body.
            MD);

        $repository = new PromptRepository($this->directory);

        $this->assertTrue($repository->has('demo.basic'));

        $this->expectException(UnknownPromptKey::class);

        $repository->get('demo.nonexistent');
    }

    private function write(string $key, string $contents): string
    {
        $path = $this->directory.'/'.$key.'.md';

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }

        file_put_contents($path, $contents."\n");

        return $path;
    }
}
