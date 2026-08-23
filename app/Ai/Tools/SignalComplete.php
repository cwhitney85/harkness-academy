<?php

namespace App\Ai\Tools;

use App\Ai\Interviews\CompletionListener;
use App\Ai\Interviews\CompletionSignal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The one interview-termination tool (spec 2.3.4, decision R3).
 *
 * Nothing here is provider-specific: it is an ordinary laravel/ai tool, so the
 * same interview runs unchanged against any provider the SDK supports.
 *
 * The model calling this and the student pressing Done are two doors into one
 * room -- both hand the listener a CompletionSignal and nothing else happens
 * here. Summarization is the listener's job (package 4).
 */
final class SignalComplete implements Tool
{
    public function __construct(private readonly CompletionListener $listener) {}

    public function name(): string
    {
        return 'signal_complete';
    }

    public function description(): string
    {
        return 'Call this once you have gathered everything you need from the student and the '
            .'interview should end. Do not call it to acknowledge a single answer.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()
                ->description('One sentence on why the interview has everything it needs.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->listener->completed(
            CompletionSignal::fromModel((string) $request->string('reason'))
        );

        return 'The interview has been marked complete.';
    }
}
