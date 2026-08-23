<?php

namespace Tests\Feature\Ai;

use App\Ai\Gateway;
use App\Ai\GatewayAgent;
use App\Ai\Interviews\CompletionListener;
use App\Ai\Interviews\CompletionSignal;
use App\Ai\Interviews\CompletionSource;
use App\Ai\Interviews\DoneButton;
use App\Ai\Prompts\PromptRepository;
use App\Ai\Tools\SignalComplete;
use App\Ai\UsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.3.4 -- one tool, two doors, one workflow.
 */
class InterviewCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');
    }

    #[Test]
    public function the_model_ends_the_interview_by_calling_signal_complete(): void
    {
        $listener = new RecordingListener;

        GatewayAgent::fake([
            new ToolCall('call-1', 'signal_complete', ['reason' => 'I have their solid, shaky and gaps.']),
            'Thanks -- that is everything I needed.',
        ]);

        $this->interview($listener);

        $this->assertCount(1, $listener->signals);
        $this->assertSame(CompletionSource::Model, $listener->signals[0]->source);
        $this->assertSame('I have their solid, shaky and gaps.', $listener->signals[0]->reason);
    }

    #[Test]
    public function the_done_button_ends_the_interview_without_asking_the_model(): void
    {
        $listener = new RecordingListener;

        GatewayAgent::fake()->preventStrayPrompts();

        $signal = (new DoneButton($listener))->press();

        $this->assertCount(1, $listener->signals);
        $this->assertSame(CompletionSource::Student, $signal->source);
        $this->assertSame($signal, $listener->signals[0]);
    }

    #[Test]
    public function both_paths_land_on_the_same_listener_with_the_same_shape(): void
    {
        $listener = new RecordingListener;

        GatewayAgent::fake([
            new ToolCall('call-1', 'signal_complete', ['reason' => 'Enough gathered.']),
            'Done.',
        ]);

        $this->interview($listener);
        (new DoneButton($listener))->press();

        $this->assertCount(2, $listener->signals);

        foreach ($listener->signals as $signal) {
            $this->assertInstanceOf(CompletionSignal::class, $signal);
            $this->assertNotSame('', $signal->reason);
        }

        $this->assertSame(
            [CompletionSource::Model, CompletionSource::Student],
            array_map(fn (CompletionSignal $s): CompletionSource => $s->source, $listener->signals),
            'The only thing that distinguishes the two paths is who ended it.',
        );
    }

    #[Test]
    public function the_tool_is_provider_neutral(): void
    {
        $tool = new SignalComplete(new RecordingListener);

        $this->assertSame('signal_complete', $tool->name());
        $this->assertSame(['reason'], array_keys($tool->schema(new JsonSchemaTypeFactory)));

        $reflection = new \ReflectionClass($tool);
        $source = (string) file_get_contents((string) $reflection->getFileName());

        $this->assertStringNotContainsString('anthropic', strtolower($source));
    }

    #[Test]
    public function the_tool_reports_completion_back_to_the_model(): void
    {
        $listener = new RecordingListener;

        $result = (new SignalComplete($listener))->handle(new Request(['reason' => '  padded  ']));

        $this->assertStringContainsString('complete', $result);
        $this->assertSame('padded', $listener->signals[0]->reason, 'The reason is stored trimmed.');
    }

    private function interview(CompletionListener $listener): void
    {
        (new Gateway(new PromptRepository(base_path('prompts')), new UsageRecorder))
            ->prompt('interview.knowledge', [
                'module' => 'Spanish',
                'subtopic' => 'Conversational Basics',
                'skill_level' => '101',
            ])
            ->feature('course.knowledge_interview')
            ->tools([new SignalComplete($listener)])
            ->stream()
            ->each(fn () => null);
    }
}

/**
 * Stands in for package 4's summarization workflow.
 */
final class RecordingListener implements CompletionListener
{
    /** @var list<CompletionSignal> */
    public array $signals = [];

    public function completed(CompletionSignal $signal): void
    {
        $this->signals[] = $signal;
    }
}
