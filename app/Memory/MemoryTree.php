<?php

namespace App\Memory;

use App\Memory\Exceptions\InvalidMemoryPath;
use App\Models\Memory;
use App\Models\MemoryVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The path API (spec 3.2) -- the only door to memory.
 *
 * Nothing outside this class may query `memories` or `memory_versions`. Every
 * method is scoped to one user: a path is meaningless without the user it
 * belongs to, and no method here can reach across users.
 *
 * Writes are workflow-initiated (ledger D3b); the model never calls `put`.
 */
final class MemoryTree
{
    /**
     * Read the document at a path.
     *
     * @param  string  $path  a valid memory path (spec 3.2); otherwise a caller bug.
     * @return string|null the content, or null when the user has no document there.
     *
     * @throws InvalidMemoryPath
     */
    public function get(User $user, string $path): ?string
    {
        $path = MemoryPath::parse($path);

        return Memory::query()
            ->where('user_id', $user->id)
            ->where('path', $path->value)
            ->value('content');
    }

    /**
     * Read the document at a path, together with the version holding it.
     *
     * What `get` returns, plus the identity of the text -- for a caller that
     * has to record which version it read (spec 4.5.4.1, ledger D10.1).
     *
     * Both halves come from the same `memory_versions` row, so the returned
     * `versionId` cannot name a version whose content is something else.
     *
     * @param  string  $path  a valid memory path (spec 3.2); otherwise a caller bug.
     * @return MemoryDocument|null null when the user has no document there.
     *
     * @throws InvalidMemoryPath
     */
    public function document(User $user, string $path): ?MemoryDocument
    {
        $path = MemoryPath::parse($path);

        $version = MemoryVersion::query()
            ->join('memories', 'memories.id', '=', 'memory_versions.memory_id')
            ->where('memories.user_id', $user->id)
            ->where('memories.path', $path->value)
            ->orderByDesc('memory_versions.version')
            ->select('memory_versions.id', 'memory_versions.content')
            ->first();

        if ($version === null) {
            return null;
        }

        return new MemoryDocument($path->value, $version->content, $version->id);
    }

    /**
     * Write the document at a path, creating it if it does not exist.
     *
     * Post: `get` returns exactly this content, and one version row records
     * this write -- an overwrite never discards what was there before.
     *
     * @throws InvalidMemoryPath
     */
    public function put(User $user, string $path, string $content): void
    {
        $path = MemoryPath::parse($path);

        DB::transaction(function () use ($user, $path, $content): void {
            $memory = Memory::query()
                ->where('user_id', $user->id)
                ->where('path', $path->value)
                ->lockForUpdate()
                ->first();

            if ($memory === null) {
                $memory = Memory::query()->create([
                    'user_id' => $user->id,
                    'path' => $path->value,
                    'content' => $content,
                ]);

                $version = 1;
            } else {
                $memory->update(['content' => $content]);

                $version = (int) MemoryVersion::query()->where('memory_id', $memory->id)->max('version') + 1;
            }

            MemoryVersion::query()->create([
                'memory_id' => $memory->id,
                'version' => $version,
                'content' => $content,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * The paths of every document at or under a prefix, in path order.
     *
     * A null prefix lists the whole tree. Prefixes match by segment, so
     * `math` lists `math/algebra/…` but never `mathematics/…`.
     *
     * @return list<string>
     *
     * @throws InvalidMemoryPath
     */
    public function list(User $user, ?string $prefix = null): array
    {
        // Byte order, not the database's locale: a tree render (spec 3.4)
        // needs a directory's children to stay contiguous, and collations that
        // weight punctuation loosely can interleave a sibling between them.
        $query = Memory::query()
            ->select('path')
            ->where('user_id', $user->id)
            ->orderByRaw('path collate "C"');

        if ($prefix !== null) {
            $prefix = MemoryPath::parse($prefix);

            $query->where(function (Builder $query) use ($prefix): void {
                $query->where('path', $prefix->value)
                    ->orWhere('path', 'like', self::escapeLike($prefix->value).'/%');
            });
        }

        $paths = [];

        foreach ($query->cursor() as $memory) {
            $paths[] = $memory->path;
        }

        return $paths;
    }

    /**
     * Neutralise the LIKE wildcards a path may legally contain.
     *
     * Paths allow `_`, which LIKE reads as "any character" -- unescaped, the
     * prefix `math/wk_1` would also list `math/wkX1`.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
