<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\PendingCall;
use App\Ai\Prompts\Exceptions\PromptVariableMismatch;
use App\Ai\Prompts\Exceptions\UnknownPromptKey;
use App\Ai\Prompts\PromptRepository;
use App\Ai\StructuredGatewayAgent;
use App\Ai\Tier;
use App\Ai\UsageRecorder;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TextResponse;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.1 -- every call goes through one service and names a tier, not a model.
 */
class GatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function it_calls_a_prompt_at_the_tier_the_prompt_declares(): void
    {
        GatewayAgent::fake(['A working-knowledge document.']);

        $response = $this->gateway()
            ->prompt('wk.create', [
                'interview_transcript' => 'Transcript.',
                'subtopic' => 'Intro',
                'skill_level' => '101',
                'prior_wk_doc' => 'None yet.',
            ])
            ->feature('course.knowledge_interview')
            ->generate();

        $this->assertSame('A working-knowledge document.', $response->text);
        $this->assertNull($response->structured);

        $this->assertSame(Tier::Mid->model(), UsageEvent::sole()->model);
    }

    #[Test]
    public function an_unknown_prompt_key_throws_before_anything_is_sent(): void
    {
        GatewayAgent::fake();

        $this->expectException(UnknownPromptKey::class);

        $this->gateway()->prompt('wk.invent_something');
    }

    #[Test]
    public function a_missing_variable_throws_before_anything_is_sent(): void
    {
        GatewayAgent::fake();

        $this->expectException(PromptVariableMismatch::class);

        $this->gateway()
            ->prompt('wk.create', ['subtopic' => 'Intro'])
            ->feature('course.knowledge_interview')
            ->generate();
    }

    #[Test]
    public function a_user_turn_carries_the_conversation_and_the_brief_becomes_the_instructions(): void
    {
        GatewayAgent::fake(['And what happens if you flip the sign?']);

        $this->interviewTurn('A letter standing in for a number.')
            ->stream()
            ->each(fn (): null => null);

        GatewayAgent::assertPrompted(
            fn (AgentPrompt $call): bool => $call->prompt === 'A letter standing in for a number.'
                && str_contains($call->agent->instructions(), 'You are interviewing a student'),
        );

        $this->assertSame(
            'interview.knowledge',
            UsageEvent::sole()->prompt_key,
            'A turn is still attributed to the prompt that framed the conversation.',
        );
    }

    #[Test]
    public function only_a_conversation_takes_a_user_turn(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('only a streamed prompt takes a user turn');

        $this->gateway()
            ->prompt('wk.create', [
                'interview_transcript' => 'Transcript.',
                'subtopic' => 'Intro',
                'skill_level' => '101',
                'prior_wk_doc' => 'None yet.',
            ])
            ->feature('course.knowledge_interview')
            ->userTurn('Something the student typed.')
            ->generate();
    }

    #[Test]
    public function an_empty_user_turn_is_a_caller_bug(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nothing to answer');

        $this->interviewTurn('   ')->stream();
    }

    #[Test]
    public function a_call_without_a_feature_label_is_refused(): void
    {
        GatewayAgent::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('no feature label');

        $this->gateway()
            ->prompt('explainer.select', [
                'selection' => 'a phrase',
                'surrounding_chunk' => 'a paragraph',
                'subtopic' => 'Intro',
                'skill_level' => '101',
            ])
            ->generate();
    }

    #[Test]
    public function a_fragment_prompt_cannot_be_sent_on_its_own(): void
    {
        GatewayAgent::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('composes into another prompt');

        $this->gateway()
            ->prompt('persona.default', ['student_name' => 'Ada', 'subtopic' => 'Intro', 'skill_level' => '101'])
            ->feature('lesson.session')
            ->generate();
    }

    #[Test]
    public function a_structured_prompt_requires_a_schema(): void
    {
        GatewayAgent::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('no schema was supplied');

        $this->gateway()
            ->prompt('syllabus.generate', [
                'wk_doc' => 'WK.',
                'criteria_doc' => 'Criteria.',
                'skill_level' => '101',
                'depth_params' => '8 lessons',
            ])
            ->feature('course.syllabus')
            ->generate();
    }

    #[Test]
    public function a_prose_prompt_refuses_a_schema(): void
    {
        GatewayAgent::fake();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot take a schema');

        $this->gateway()
            ->prompt('wk.create', [
                'interview_transcript' => 'Transcript.',
                'subtopic' => 'Intro',
                'skill_level' => '101',
                'prior_wk_doc' => 'None yet.',
            ])
            ->feature('course.knowledge_interview')
            ->schema(fn ($schema): array => ['ok' => $schema->boolean()])
            ->generate();
    }

    #[Test]
    public function a_structured_prompt_returns_structured_data(): void
    {
        StructuredGatewayAgent::fake([['lessons' => ['one', 'two']]]);

        $response = $this->gateway()
            ->prompt('syllabus.generate', [
                'wk_doc' => 'WK.',
                'criteria_doc' => 'Criteria.',
                'skill_level' => '101',
                'depth_params' => '8 lessons',
            ])
            ->feature('course.syllabus')
            ->schema(fn ($schema): array => ['lessons' => $schema->array()->items($schema->string())])
            ->generate();

        $this->assertSame(['lessons' => ['one', 'two']], $response->structured);
        $this->assertSame(Tier::Top->model(), UsageEvent::sole()->model);
    }

    #[Test]
    public function a_session_tier_prompt_runs_at_the_tier_of_its_session(): void
    {
        GatewayAgent::fake(['Section prose.']);

        $stream = $this->section()->sessionTier(Tier::Top)->stream();

        foreach ($stream as $event) {
            // Drain the stream; the usage row is written when it finishes.
        }

        $event = UsageEvent::sole();

        $this->assertSame(Tier::Top->model(), $event->model);
        $this->assertSame(Tier::Top->value, $event->tier);
    }

    #[Test]
    public function a_session_tier_prompt_without_a_session_tier_is_a_caller_bug(): void
    {
        GatewayAgent::fake(['Section prose.'])->preventStrayPrompts();

        $this->expectException(\InvalidArgumentException::class);

        $this->section()->stream();
    }

    #[Test]
    public function a_streamed_prompt_cannot_be_generated_in_one_shot(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('call stream() instead');

        $this->section()->sessionTier(Tier::Mid)->generate();
    }

    #[Test]
    public function a_prose_prompt_cannot_be_streamed(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be streamed');

        $this->gateway()
            ->prompt('wk.create', [
                'interview_transcript' => 'Transcript.',
                'subtopic' => 'Intro',
                'skill_level' => '101',
            ])
            ->feature('course.knowledge_interview')
            ->stream();
    }

    #[Test]
    public function render_composes_a_prompt_without_calling_anything(): void
    {
        GatewayAgent::fake()->preventStrayPrompts();

        $rendered = $this->gateway()->render('persona.default', [
            'student_name' => 'Ada',
            'subtopic' => 'Intro',
            'skill_level' => '101',
        ]);

        $this->assertStringContainsString('Ada', $rendered);
        $this->assertSame(0, UsageEvent::count(), 'Rendering is not a call and must not be billed.');
    }

    #[Test]
    public function the_usage_row_records_who_the_call_was_for(): void
    {
        $user = User::factory()->create();

        GatewayAgent::fake([
            new TextResponse('Explained.', new Usage(120, 40, 0, 0), new Meta('anthropic', Tier::Cheap->model())),
        ]);

        $this->gateway()
            ->prompt('explainer.select', [
                'selection' => 'a phrase',
                'surrounding_chunk' => 'a paragraph',
                'subtopic' => 'Intro',
                'skill_level' => '101',
            ])
            ->feature('lesson.explainer')
            ->for($user)
            ->generate();

        $event = UsageEvent::sole();

        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('lesson.explainer', $event->feature);
        $this->assertSame('explainer.select', $event->prompt_key);
    }

    private function section(): PendingCall
    {
        return $this->gateway()
            ->prompt('lesson.section', [
                'section_index' => '1',
                'section_title' => 'Greetings',
                'section_concepts' => 'greeting-formal',
            ])
            ->feature('lesson.section');
    }

    /**
     * One turn of a conversation: the brief frames it, the text is the turn.
     */
    private function interviewTurn(string $text): PendingCall
    {
        return $this->gateway()
            ->prompt('interview.knowledge', [
                'module' => 'Maths',
                'subtopic' => 'Algebra',
                'skill_level' => '101',
            ])
            ->feature('course.knowledge_interview')
            ->userTurn($text);
    }

    private function gateway(): Gateway
    {
        return new Gateway(new PromptRepository(base_path('prompts')), new UsageRecorder);
    }
}
