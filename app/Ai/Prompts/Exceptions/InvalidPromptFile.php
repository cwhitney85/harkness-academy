<?php

namespace App\Ai\Prompts\Exceptions;

class InvalidPromptFile extends PromptException
{
    public static function for(string $path, string $problem): self
    {
        return new self("Prompt file [{$path}] is invalid: {$problem}");
    }
}
