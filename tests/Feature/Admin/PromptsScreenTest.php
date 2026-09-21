<?php

namespace Tests\Feature\Admin;

use App\Admin\AdminActionType;
use App\Ai\Prompts\PromptRepository;
use App\Ai\Prompts\PromptSource;
use App\Ai\Tier;
use App\Models\AdminAction;
use App\Models\Prompt;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 15.2.1-15.2.3 -- Admin → Prompts lists the 2.3.2 inventory with what it
 * has cost, and is the one place a prompt's text is written (ledger D65).
 *
 * The fixture is the real `prompts/` tree, seeded by `PromptRegistrySeeder`
 * ([T18](docs/lessons.md)), so the counts here are the inventory's own.
 */
class PromptsScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_screen_renders_every_prompt_with_its_active_version_and_seven_days_of_usage(): void
    {
        $this->served('explainer.select', cost: 0.25);
        $this->served('explainer.select', cost: 0.75);
        $this->served('explainer.select', cost: 9.00, ago: 8);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/prompts')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/prompts')
                ->where('days', 7)
                ->has('prompts', 18)
                // Ordered by key, so `explainer.select` is the fourth of the
                // eighteen and `exam.final` the third.
                ->where('prompts.3.key', 'explainer.select')
                ->where('prompts.3.output', 'markdown')
                ->where('prompts.3.version', 1)
                ->where('prompts.3.source', 'file')
                ->where('prompts.3.tier', 'cheap')
                // The eight-day-old call is outside the window.
                ->where('prompts.3.calls', 2)
                ->where('prompts.3.cost', 1)
                ->where('prompts.2.calls', 0));
    }

    #[Test]
    public function one_prompt_shows_its_contract_body_and_editable_tiers(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/prompts/explainer.select')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/prompt')
                ->where('prompt.key', 'explainer.select')
                ->where('prompt.vars', ['selection', 'surrounding_chunk', 'subtopic', 'skill_level'])
                ->where('prompt.version', 1)
                ->where('tiers', ['cheap', 'mid', 'top'])
                ->where('prompt.body', fn (string $body): bool => str_contains($body, '{{ selection }}')));
    }

    #[Test]
    public function saving_a_body_creates_a_version_activates_it_and_logs_it(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('explainer.select');

        $this->actingAs($admin)
            ->put('/admin/prompts/explainer.select', [
                'body' => $this->body($prompt, 'In one sentence.'),
                'tier' => 'cheap',
            ])
            ->assertRedirect('/admin/prompts/explainer.select')
            ->assertSessionHasNoErrors();

        $active = $this->prompt('explainer.select')->activeVersion;

        $this->assertSame(2, $active->number);
        $this->assertSame(PromptSource::Admin, $active->source);
        $this->assertSame($admin->id, $active->author_id);
        $this->assertNull($active->file_hash);
        $this->assertStringContainsString('In one sentence.', $active->body);

        $this->assertStringContainsString(
            'In one sentence.',
            app(PromptRepository::class)->get('explainer.select')->body,
            'The repository cache was not cleared by the save.',
        );

        $logged = AdminAction::query()->sole();

        $this->assertSame(AdminActionType::PromptEdited, $logged->action);
        $this->assertSame('explainer.select', $logged->payload['key']);
        $this->assertSame(1, $logged->payload['was']['version']);
        $this->assertSame(2, $logged->payload['now']['version']);
    }

    #[Test]
    public function saving_a_tier_moves_the_tier_and_leaves_the_body_alone(): void
    {
        $prompt = $this->prompt('explainer.select');

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/explainer.select', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'mid',
            ])
            ->assertSessionHasNoErrors();

        $active = $this->prompt('explainer.select')->activeVersion;

        $this->assertSame(Tier::Mid, $active->tier);
        $this->assertSame($prompt->activeVersion->body, $active->body);
    }

    #[Test]
    public function a_body_that_drops_a_declared_var_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/explainer.select', [
                'body' => 'Explain {{ selection }} for a {{ skill_level }} student.',
                'tier' => 'cheap',
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, $this->prompt('explainer.select')->versions()->count());
        $this->assertSame(0, AdminAction::query()->count());
    }

    #[Test]
    public function a_body_that_uses_an_undeclared_var_is_refused(): void
    {
        $prompt = $this->prompt('explainer.select');

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/explainer.select', [
                'body' => $this->body($prompt, 'Also consider {{ homework_doc }}.'),
                'tier' => 'cheap',
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(1, $prompt->versions()->count());
    }

    /**
     * Spec 15.2.3 -- `interview.knowledge` streams inside the request, where
     * the host's 60-second silence ceiling applies (ledger D63).
     */
    #[Test]
    public function the_editor_refuses_top_for_a_prompt_that_streams_inside_the_request(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('interview.knowledge');

        $this->actingAs($admin)->get('/admin/prompts/interview.knowledge')
            ->assertInertia(fn (Assert $page) => $page->where('tiers', ['cheap', 'mid']));

        $this->actingAs($admin)
            ->put('/admin/prompts/interview.knowledge', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'top',
            ])
            ->assertSessionHasErrors('tier');

        $this->actingAs($admin)
            ->put('/admin/prompts/interview.knowledge', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'cheap',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Spec 15.2.3 -- `lesson.section` declares `tier: session`, which is not a
     * tier it chooses for itself: it inherits `lesson.system`'s.
     */
    #[Test]
    public function a_session_tier_prompt_has_no_editable_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $prompt = $this->prompt('lesson.section');

        $this->actingAs($admin)->get('/admin/prompts/lesson.section')
            ->assertInertia(fn (Assert $page) => $page->where('tiers', []));

        $this->actingAs($admin)
            ->put('/admin/prompts/lesson.section', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'mid',
            ])
            ->assertSessionHasErrors('tier');

        $this->actingAs($admin)
            ->put('/admin/prompts/lesson.section', ['body' => $this->body($prompt, 'Keep it short.')])
            ->assertSessionHasNoErrors();

        $this->assertSame(Tier::Session, $this->prompt('lesson.section')->activeVersion->tier);
    }

    /**
     * Spec 15.2.3 -- `lesson.system` carries the tier the streamed lesson
     * session runs at, so it is held to the same ceiling.
     */
    #[Test]
    public function the_lesson_system_prompt_cannot_move_the_session_to_top(): void
    {
        $prompt = $this->prompt('lesson.system');

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/prompts/lesson.system', [
                'body' => $prompt->activeVersion->body,
                'tier' => 'top',
            ])
            ->assertSessionHasErrors('tier');
    }

    private function prompt(string $key): Prompt
    {
        return Prompt::query()->with('activeVersion')->where('key', $key)->sole();
    }

    /**
     * The active body with a line added, so a save changes the text without
     * touching which variables it interpolates.
     */
    private function body(Prompt $prompt, string $addition): string
    {
        return $prompt->activeVersion->body."\n".$addition."\n";
    }

    private function served(string $key, float $cost, int $ago = 0): void
    {
        UsageEvent::query()->create([
            'feature' => 'lesson',
            'prompt_key' => $key,
            'provider' => 'anthropic',
            'tier' => 'cheap',
            'model' => Tier::Cheap->model(),
            'outcome' => 'ok',
            'latency_ms' => 100,
            'cost_usd' => $cost,
            'created_at' => now()->subDays($ago),
        ]);
    }
}
