<?php

namespace App\Console\Commands;

use App\Memory\Exceptions\InvalidMemoryPath;
use App\Memory\MemoryTree;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The memory inspector of spec 3.4.
 *
 * The only admin UI v1 ships: with no path it prints the user's tree, with a
 * path it prints that document. It reads through the path API like every
 * other consumer -- an inspector that queried the tables directly would stop
 * showing what the model actually sees (3.3 rollups are rendered, not stored).
 */
class MemoryShowCommand extends Command
{
    protected $signature = 'memory:show
        {user : The user id or email address}
        {path? : A path in the tree; omit to print the whole tree}';

    protected $description = 'Spec 3.4: print a user\'s memory tree, or one document in it';

    public function handle(MemoryTree $tree): int
    {
        $identifier = (string) $this->argument('user');

        $user = $this->resolveUser($identifier);

        if ($user === null) {
            $this->components->error("No user matches [{$identifier}].");

            return self::FAILURE;
        }

        $path = $this->argument('path');

        try {
            return is_string($path)
                ? $this->showDocument($tree, $user, $path)
                : $this->showTree($tree, $user);
        } catch (InvalidMemoryPath $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function showDocument(MemoryTree $tree, User $user, string $path): int
    {
        $content = $tree->get($user, $path);

        if ($content === null) {
            $this->components->error("{$user->email} has no document at [{$path}].");

            return self::FAILURE;
        }

        $this->line($content);

        return self::SUCCESS;
    }

    private function showTree(MemoryTree $tree, User $user): int
    {
        $paths = $tree->list($user);

        if ($paths === []) {
            $this->components->warn("{$user->email} has an empty memory tree.");

            return self::SUCCESS;
        }

        $this->components->info("{$user->email}");

        foreach ($this->indent($paths) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    /**
     * Render sorted paths as a tree, printing each directory segment once.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function indent(array $paths): array
    {
        $lines = [];
        $previous = [];

        foreach ($paths as $path) {
            $segments = explode('/', $path);
            $diverged = false;

            foreach ($segments as $depth => $segment) {
                // Once a segment differs from the last path, every deeper one
                // belongs to a different directory and must be printed again.
                if (! $diverged && ($previous[$depth] ?? null) === $segment) {
                    continue;
                }

                $diverged = true;

                $lines[] = str_repeat('  ', $depth).$segment;
            }

            $previous = $segments;
        }

        return $lines;
    }

    private function resolveUser(string $identifier): ?User
    {
        return User::query()
            ->when(
                ctype_digit($identifier),
                fn ($query) => $query->whereKey((int) $identifier),
                fn ($query) => $query->where('email', $identifier),
            )
            ->first();
    }
}
