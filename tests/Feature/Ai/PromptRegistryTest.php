<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\Prompts\Exceptions\PromptVariableMismatch;
use App\Ai\Prompts\Exceptions\UnknownPromptKey;
use App\Ai\Prompts\PromptFiles;
use App\Ai\Prompts\PromptRepository;
use App\Ai\Prompts\PromptSource;
use App\Ai\Tier;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\UsageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 15.1.5, 15.1.6 -- what the gateway reads is the active version, cached
 * until a write commits, and what it costs is written against that version.
 */
class PromptRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function the_registry_holds_every_prompt_file(): void
    {
        $this->assertSame(
            (new PromptFiles(base_path('prompts')))->keys(),
            $this->prompts()->keys(),
            'Spec 15.1.2: every file in prompts/ is registered by the sync that runs at test bootstrap.',
        );
    }

    #[Test]
    public function the_command_makes_no_version_when_nothing_changed(): void
    {
        $versions = PromptVersion::query()->count();

        $this->artisan('prompts:sync')->assertSuccessful();

        $this->assertSame($versions, PromptVersion::query()->count());
    }

    #[Test]
    public function an_unknown_key_throws(): void
    {
        $this->assertTrue($this->prompts()->has('explainer.select'));

        $this->expectException(UnknownPromptKey::class);

        $this->prompts()->get('explainer.nonexistent');
    }

    #[Test]
    public function rendering_fails_on_a_missing_var(): void
    {
        $this->expectException(PromptVariableMismatch::class);
        $this->expectExceptionMessage('missing [skill_level]');

        $this->prompts()->get('explainer.select')->render([
            'selection' => 'a phrase',
            'surrounding_chunk' => 'a paragraph',
            'subtopic' => 'Intro',
        ]);
    }

    #[Test]
    public function rendering_fails_on_an_undeclared_var(): void
    {
        $this->expectException(PromptVariableMismatch::class);
        $this->expectExceptionMessage('undeclared [nope]');

        $this->prompts()->get('explainer.select')->render([
            'selection' => 'a phrase',
            'surrounding_chunk' => 'a paragraph',
            'subtopic' => 'Intro',
            'skill_level' => '101',
            'nope' => 'b',
        ]);
    }

    #[Test]
    public function a_live_edit_is_what_the_next_call_renders_and_what_its_usage_row_names(): void
    {
        $prompt = Prompt::query()->with('activeVersion')->where('key', 'explainer.select')->sole();

        $edit = $prompt->versions()->create([
            'number' => $prompt->activeVersion->number + 1,
            'body' => 'Explain {{ selection }} from {{ surrounding_chunk }} of {{ subtopic }} at {{ skill_level }}.',
            'tier' => Tier::Cheap,
            'source' => PromptSource::Admin,
        ]);

        $prompt->activate($edit);

        $sent = null;

        GatewayAgent::fake(function (string $message) use (&$sent): TextResponse {
            $sent = $message;

            return new TextResponse(
                'Explained.',
                new Usage(promptTokens: 20, completionTokens: 5),
                new Meta('anthropic', Tier::Cheap->model()),
            );
        });

        $this->explain();

        $this->assertStringStartsWith('Explain a phrase from', $sent, 'The call rendered the active version.');
        $this->assertSame($edit->id, UsageEvent::sole()->prompt_version_id);
    }

    #[Test]
    public function a_usage_row_names_the_version_that_ran(): void
    {
        GatewayAgent::fake([
            new TextResponse('Explained.', new Usage(20, 5), new Meta('anthropic', Tier::Cheap->model())),
        ]);

        $this->explain();

        $this->assertSame(
            $this->prompts()->version('explainer.select'),
            UsageEvent::sole()->prompt_version_id,
        );
    }

    /**
     * Spec 15.1.5 -- a call landing between the clear and the commit would
     * re-cache the old version forever, so the clear waits for the commit.
     */
    #[Test]
    public function the_registry_is_cleared_only_once_a_save_commits(): void
    {
        $this->prompts()->get('explainer.select');

        DB::transaction(function (): void {
            $prompt = Prompt::query()->with('activeVersion')->where('key', 'explainer.select')->sole();

            $prompt->activate($prompt->versions()->create([
                'number' => 2,
                'body' => $prompt->activeVersion->body,
                'tier' => Tier::Cheap,
                'source' => PromptSource::Admin,
            ]));

            $this->assertTrue(Cache::has(Prompt::CACHE_KEY), 'Cleared before the save committed.');
        });

        $this->assertFalse(Cache::has(Prompt::CACHE_KEY), 'Not cleared once the save committed.');
    }

    private function explain(): void
    {
        app(Gateway::class)
            ->prompt('explainer.select', [
                'selection' => 'a phrase',
                'surrounding_chunk' => 'a paragraph',
                'subtopic' => 'Intro',
                'skill_level' => '101',
            ])
            ->feature('lesson.explainer')
            ->generate();
    }

    private function prompts(): PromptRepository
    {
        return app(PromptRepository::class);
    }
}
