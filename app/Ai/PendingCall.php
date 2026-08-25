<?php

namespace App\Ai;

use App\Ai\Prompts\PromptDefinition;
use App\Ai\Prompts\PromptOutput;
use App\Models\UsageEvent;
use App\Models\User;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use Throwable;

/**
 * One AI call being assembled (spec 2.1).
 *
 * Preconditions checked before anything reaches a provider:
 *   - the prompt key exists in the inventory (PromptRepository::get)
 *   - the rendered prompt supplies exactly its declared vars (2.3.1)
 *   - `feature` is set, so the usage row can be attributed (2.2)
 *   - the tier resolves to a model (2.1.1)
 *   - a schema is supplied if and only if the prompt declares json output
 *
 * Postcondition: exactly one usage_events row per attempt, written even when
 * the provider throws.
 */
final class PendingCall
{
    private string $feature = '';

    private ?int $userId = null;

    private string $instructions = '';

    /** @var iterable<mixed> */
    private iterable $messages = [];

    /** @var list<Tool> */
    private array $tools = [];

    private ?Tier $sessionTier = null;

    private ?string $userTurn = null;

    /** @var (Closure(JsonSchema): array<string, Type>)|null */
    private ?Closure $schema = null;

    private ?int $timeout = null;

    /**
     * @param  array<string, string|\Stringable>  $vars
     */
    public function __construct(
        private readonly PromptDefinition $prompt,
        private readonly UsageRecorder $recorder,
        private readonly array $vars,
    ) {}

    /**
     * Label the usage row. Required -- 2.2's cost report groups on this.
     */
    public function feature(string $feature): self
    {
        $this->feature = $feature;

        return $this;
    }

    public function for(User|int|null $user): self
    {
        $this->userId = $user instanceof User ? $user->id : $user;

        return $this;
    }

    /**
     * The system prompt. For a lesson session this is the rendered
     * `lesson.system` -- the stable, cached prefix of spec 2.3.3.
     *
     * @param  array<string, string|\Stringable>  $vars  Only used when a definition is passed.
     */
    public function instructions(string|PromptDefinition $instructions, array $vars = []): self
    {
        $this->instructions = $instructions instanceof PromptDefinition
            ? $instructions->render($vars)
            : $instructions;

        return $this;
    }

    /**
     * @param  iterable<mixed>  $messages
     */
    public function messages(iterable $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Send this text as the newest user message, in place of the rendered
     * prompt body.
     *
     * A conversational prompt (`output: stream`) is a brief, not a turn: it
     * tells the model how to run the conversation, and every turn after the
     * first carries the person's own words instead. So when a turn is set the
     * brief renders into the instructions and this text is what the provider
     * receives as the message. `messages()` supplies everything said before.
     *
     * Restricted to streamed prompts on purpose -- it is the one way to send
     * text a prompt file did not produce, and only a conversation has any.
     *
     * Pre: the prompt declares `output: stream` and `$text` is not blank.
     * Post: the provider gets [rendered brief as instructions | messages() as
     *       history | this as the newest user message], and the usage row is
     *       still attributed to this prompt key.
     */
    public function userTurn(string $text): self
    {
        $this->userTurn = $text;

        return $this;
    }

    /**
     * @param  list<Tool>  $tools
     */
    public function tools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * The tier of the session this call belongs to, for a `tier: session`
     * prompt such as `lesson.section`.
     */
    public function sessionTier(Tier $tier): self
    {
        $this->sessionTier = $tier;

        return $this;
    }

    /**
     * @param  Closure(JsonSchema): array<string, Type>  $schema
     */
    public function schema(Closure $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * The tier this call will actually run at.
     */
    public function tier(): Tier
    {
        return $this->prompt->tier->resolve($this->sessionTier);
    }

    /**
     * Send the call and wait for the whole reply.
     */
    public function generate(): GatewayResponse
    {
        $this->assertCallable(PromptOutput::Stream);

        $tier = $this->tier();
        $model = $tier->model();
        $agent = $this->agent();

        $startedAt = hrtime(true);

        try {
            $response = $agent->prompt(
                $this->outgoing(),
                model: $model,
                timeout: $this->timeout,
            );
        } catch (Throwable $e) {
            $this->recordFailure($tier, $model, $startedAt);

            throw $e;
        }

        return new GatewayResponse(
            text: $response->text,
            structured: $response instanceof StructuredAgentResponse ? $response->structured : null,
            toolCalls: $response->toolCalls,
            usage: $this->record($tier, $model, $response, $startedAt),
        );
    }

    /**
     * Send the call and stream the reply.
     *
     * The usage row is written when the stream finishes, since that is the
     * first moment the token counts exist.
     */
    public function stream(): StreamableAgentResponse
    {
        $this->assertStreamable();

        $tier = $this->tier();
        $model = $tier->model();

        $startedAt = hrtime(true);

        return $this->agent()
            ->stream($this->outgoing(), model: $model, timeout: $this->timeout)
            ->then(fn (AgentResponse $response) => $this->record($tier, $model, $response, $startedAt));
    }

    private function agent(): GatewayAgent
    {
        $instructions = $this->systemPrompt();

        return $this->schema instanceof Closure
            ? new StructuredGatewayAgent($instructions, $this->messages, $this->tools, $this->schema)
            : new GatewayAgent($instructions, $this->messages, $this->tools);
    }

    /**
     * The text the provider receives as the newest user message.
     */
    private function outgoing(): string
    {
        return $this->userTurn ?? $this->prompt->render($this->vars);
    }

    /**
     * The system prompt.
     *
     * A conversation whose caller named no instructions gets the prompt's own
     * brief -- the turn displaced it from the message slot, and dropping it
     * would leave the model running the interview with no instructions at all.
     */
    private function systemPrompt(): string
    {
        if ($this->instructions !== '') {
            return $this->instructions;
        }

        return $this->userTurn === null ? '' : $this->prompt->render($this->vars);
    }

    private function record(Tier $tier, string $model, AgentResponse $response, int $startedAt): UsageEvent
    {
        return $this->recorder->record(
            feature: $this->feature,
            promptKey: $this->prompt->key,
            provider: $response->meta->provider ?? (string) config('ai.default'),
            tier: $tier,
            model: $model,
            usage: $response->usage,
            latencyMs: $this->elapsedMs($startedAt),
            userId: $this->userId,
        );
    }

    private function recordFailure(Tier $tier, string $model, int $startedAt): void
    {
        $this->recorder->record(
            feature: $this->feature,
            promptKey: $this->prompt->key,
            provider: (string) config('ai.default'),
            tier: $tier,
            model: $model,
            usage: new Usage,
            latencyMs: $this->elapsedMs($startedAt),
            userId: $this->userId,
        );
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    private function assertStreamable(): void
    {
        $this->assertCallable(null);

        if ($this->prompt->output !== PromptOutput::Stream) {
            throw new LogicException(
                "Prompt [{$this->prompt->key}] declares output [{$this->prompt->outputContract()}] and cannot be streamed."
            );
        }
    }

    /**
     * @param  PromptOutput|null  $forbidden  An output this entry point rejects.
     */
    private function assertCallable(?PromptOutput $forbidden): void
    {
        if (! $this->prompt->output->isCallable()) {
            throw new LogicException(
                "Prompt [{$this->prompt->key}] declares output [{$this->prompt->outputContract()}]; "
                .'it composes into another prompt and is never sent on its own.'
            );
        }

        if ($forbidden !== null && $this->prompt->output === $forbidden) {
            throw new LogicException(
                "Prompt [{$this->prompt->key}] declares output [{$this->prompt->outputContract()}]; call stream() instead."
            );
        }

        if ($this->userTurn !== null) {
            if ($this->prompt->output !== PromptOutput::Stream) {
                throw new LogicException(
                    "Prompt [{$this->prompt->key}] declares output [{$this->prompt->outputContract()}] and is not a "
                    .'conversation; only a streamed prompt takes a user turn.'
                );
            }

            if (trim($this->userTurn) === '') {
                throw new LogicException(
                    "Call to prompt [{$this->prompt->key}] supplies an empty user turn; there is nothing to answer."
                );
            }
        }

        if ($this->feature === '') {
            throw new LogicException(
                "Call to prompt [{$this->prompt->key}] has no feature label; usage_events (spec 2.2) requires one."
            );
        }

        $declaresJson = $this->prompt->output === PromptOutput::Json;

        if ($declaresJson && ! $this->schema instanceof Closure) {
            throw new LogicException(
                "Prompt [{$this->prompt->key}] declares output [{$this->prompt->outputContract()}] but no schema was supplied."
            );
        }

        if (! $declaresJson && $this->schema instanceof Closure) {
            throw new LogicException(
                "Prompt [{$this->prompt->key}] declares output [{$this->prompt->outputContract()}] and cannot take a schema."
            );
        }
    }
}
