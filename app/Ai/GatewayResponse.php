<?php

namespace App\Ai;

use App\Models\UsageEvent;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\Data\ToolCall;

/**
 * What a completed gateway call returns (spec 2.1).
 *
 * `usage` is the row already written to usage_events, so a caller that wants
 * to attribute cost never has to re-query for it.
 */
final readonly class GatewayResponse
{
    /**
     * @param  array<string, mixed>|null  $structured
     * @param  Collection<int, ToolCall>  $toolCalls
     */
    public function __construct(
        public string $text,
        public ?array $structured,
        public Collection $toolCalls,
        public UsageEvent $usage,
    ) {}
}
