<?php

namespace App\Ai\Prompts;

use App\Ai\Prompts\Exceptions\UnknownPromptKey;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * The prompt inventory as `prompts/` holds it (spec 2.3.2) -- where every
 * prompt's contract comes from, and where its first body comes from
 * (ledger D65).
 *
 * A file's key is its path: `prompts/interview/knowledge.md` is
 * `interview.knowledge`. The loader rejects any file whose frontmatter key
 * disagrees, so the directory tree and the inventory cannot drift apart, and
 * a key is enough to name its file.
 *
 * What runs is the active version in the database, not these files: a file's
 * body seeds version 1 and is never read again, so it goes stale as soon as
 * the editor is used. Read what runs through `PromptRepository`.
 */
final class PromptFiles
{
    /** @var array<string, PromptDefinition>|null */
    private ?array $prompts = null;

    public function __construct(
        private readonly string $directory,
        private readonly PromptLoader $loader = new PromptLoader,
    ) {}

    /**
     * @throws UnknownPromptKey
     */
    public function get(string $key): PromptDefinition
    {
        return $this->all()[$key] ?? throw UnknownPromptKey::for($key, $this->keys());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return array<string, PromptDefinition>
     */
    public function all(): array
    {
        if ($this->prompts !== null) {
            return $this->prompts;
        }

        $prompts = [];

        foreach ($this->files() as $file) {
            $key = $this->keyFor($file);

            $prompts[$key] = $this->loader->load($file->getPathname(), $key);
        }

        ksort($prompts);

        return $this->prompts = $prompts;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * Where the file behind a key lives, whether or not it exists yet.
     */
    public function path(string $key): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .str_replace('.', DIRECTORY_SEPARATOR, $key)
            .'.md';
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function files(): iterable
    {
        if (! is_dir($this->directory)) {
            return [];
        }

        return Finder::create()->files()->in($this->directory)->name('*.md')->sortByName();
    }

    private function keyFor(SplFileInfo $file): string
    {
        $relative = Str::of($file->getPathname())
            ->after(rtrim($this->directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            ->beforeLast('.md');

        return $relative->explode(DIRECTORY_SEPARATOR)->implode('.');
    }
}
