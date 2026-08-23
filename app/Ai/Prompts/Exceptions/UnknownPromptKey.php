<?php

namespace App\Ai\Prompts\Exceptions;

class UnknownPromptKey extends PromptException
{
    /**
     * @param  list<string>  $known
     */
    public static function for(string $key, array $known): self
    {
        sort($known);

        return new self("No prompt is registered under the key [{$key}]. Known keys: ".implode(', ', $known).'.');
    }
}
