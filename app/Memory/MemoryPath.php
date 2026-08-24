<?php

namespace App\Memory;

use App\Memory\Exceptions\InvalidMemoryPath;
use Stringable;

/**
 * A validated location in the memory tree (spec 3.2).
 *
 * Paths look like files to the model, so they are checked like filenames:
 * relative, slash-separated, no empty or dot segments, nothing that could be
 * read as an escape upward. Constructing this object is the only way a path
 * reaches the `memories` table, so every stored path satisfies these rules.
 */
final readonly class MemoryPath implements Stringable
{
    /**
     * The `path` column's width -- longer paths cannot round-trip.
     */
    private const MAX_LENGTH = 255;

    private const SEGMENT = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /**
     * @param  list<string>  $segments
     */
    private function __construct(
        public string $value,
        public array $segments,
    ) {}

    /**
     * @throws InvalidMemoryPath
     */
    public static function parse(string $path): self
    {
        if ($path === '') {
            throw InvalidMemoryPath::for($path, 'it is empty.');
        }

        if (strlen($path) > self::MAX_LENGTH) {
            throw InvalidMemoryPath::for($path, 'it is longer than '.self::MAX_LENGTH.' characters.');
        }

        if (str_starts_with($path, '/') || str_ends_with($path, '/')) {
            throw InvalidMemoryPath::for($path, 'it must be relative and must not end in a slash.');
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw InvalidMemoryPath::for($path, 'it has an empty segment.');
            }

            if ($segment === '.' || $segment === '..') {
                throw InvalidMemoryPath::for($path, 'dot segments are not allowed.');
            }

            if (preg_match(self::SEGMENT, $segment) !== 1) {
                throw InvalidMemoryPath::for(
                    $path,
                    "segment [{$segment}] must start with a letter or digit and contain only letters, digits, dots, dashes and underscores."
                );
            }
        }

        return new self($path, $segments);
    }

    /**
     * The first segment: the module a document belongs to.
     */
    public function module(): string
    {
        return $this->segments[0];
    }

    public function filename(): string
    {
        return $this->segments[count($this->segments) - 1];
    }

    public function depth(): int
    {
        return count($this->segments);
    }

    /**
     * Whether this path lies at or under the given prefix.
     *
     * Matching is by segment, so `math` contains `math/algebra.md` but not
     * `mathematics/algebra.md`.
     */
    public function isUnder(MemoryPath $prefix): bool
    {
        return $this->value === $prefix->value
            || str_starts_with($this->value, $prefix->value.'/');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
