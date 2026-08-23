<?php

namespace App\Ai;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * A GatewayAgent whose reply is constrained to a JSON schema.
 *
 * The schema itself belongs to the package that owns the contract (spec 4.5.1
 * syllabus, 5.2.1 checkpoint, 6.1 quiz); the gateway only checks that the
 * prompt's frontmatter declared a `json/<schema>` output before accepting one.
 */
final class StructuredGatewayAgent extends GatewayAgent implements HasStructuredOutput
{
    /**
     * @param  iterable<mixed>  $agentMessages
     * @param  iterable<mixed>  $agentTools
     * @param  Closure(JsonSchema): array<string, Type>  $definition
     */
    public function __construct(
        string $agentInstructions,
        iterable $agentMessages,
        iterable $agentTools,
        private readonly Closure $definition,
    ) {
        parent::__construct($agentInstructions, $agentMessages, $agentTools);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ($this->definition)($schema);
    }
}
