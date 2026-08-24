<?php

namespace App\Memory\Exceptions;

class InvalidMemoryPath extends MemoryException
{
    public static function for(string $path, string $problem): self
    {
        return new self("Memory path [{$path}] is invalid: {$problem}");
    }
}
