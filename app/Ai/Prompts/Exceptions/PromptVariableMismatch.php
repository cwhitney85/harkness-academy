<?php

namespace App\Ai\Prompts\Exceptions;

class PromptVariableMismatch extends PromptException
{
    /**
     * @param  list<string>  $missing
     * @param  list<string>  $undeclared
     */
    public static function for(string $key, array $missing, array $undeclared): self
    {
        $problems = [];

        if ($missing !== []) {
            $problems[] = 'missing ['.implode(', ', $missing).']';
        }

        if ($undeclared !== []) {
            $problems[] = 'undeclared ['.implode(', ', $undeclared).']';
        }

        return new self("Cannot render prompt [{$key}]: ".implode('; ', $problems).'.');
    }
}
