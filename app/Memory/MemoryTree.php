<?php

namespace App\Memory;

use App\Admin\ViewingAs;
use App\Memory\Exceptions\InvalidMemoryPath;
use App\Models\Course;
use App\Models\Memory;
use App\Models\MemoryVersion;
use App\Models\Removal;
use App\Models\Subtopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            ->whereNull('memories.deleted_at')
            ->orderByDesc('memory_versions.version')
            ->select('memory_versions.id', 'memory_versions.content')
            ->first();

        if ($version === null) {
            return null;
        }

        return new MemoryDocument($path->value, $version->content, $version->id);
    }

    /**
     * Write a document for the course whose workflow is writing it, creating
     * it if it does not exist (spec 16.3.3).
     *
     * The owner follows from the path: the course's working knowledge belongs
     * to its subtopic (D8), anything under the course's own folder belongs to
     * the course (D58), and any other path is refused -- a course cannot write
     * into another course's folder or another subtopic's knowledge.
     *
     * Post: `get` returns exactly this content, and one version row records
     * this write and the course that made it -- an overwrite never discards
     * what was there before.
     *
     * @throws InvalidMemoryPath
     */
    public function put(Course $course, string $path, string $content): void
    {
        $path = MemoryPath::parse($path);

        $owner = match (true) {
            $path->value === $course->workingKnowledgePath() => $course->subtopic,
            str_starts_with($path->value, $course->materialsPrefix().'/') => $course,
            default => throw InvalidMemoryPath::for($path->value, "course #{$course->id} writes only its own documents and its subtopic's working knowledge."),
        };

        DB::transaction(function () use ($course, $owner, $path, $content): void {
            $memory = Memory::query()
                ->where('user_id', $course->user_id)
                ->where('path', $path->value)
                ->lockForUpdate()
                ->first()
                ?? new Memory(['user_id' => $course->user_id, 'path' => $path->value]);

            $version = $memory->exists ? 1 + (int) $memory->versions()->max('version') : 1;

            $memory->owner()->associate($owner);
            $memory->content = $content;
            $memory->save();

            MemoryVersion::query()->create([
                'memory_id' => $memory->id,
                'course_id' => $course->id,
                'version' => $version,
                'content' => $content,
                // The admin acting as this student, if one is (spec 13.4.3).
                'acting_user_id' => ViewingAs::actingUserId(),
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
     * Every document a student has, removed ones included and marked, each
     * with its whole history in version order -- the admin's read of the tree
     * (spec 16.3.4).
     *
     * Plain arrays, not models: the caller holds nothing it could save.
     *
     * @return list<array{path: string, removed: bool, versions: list<array{version: int, course_id: int, acting_user_id: int|null, content: string, created_at: Carbon}>}>
     */
    public function history(User $user): array
    {
        return array_values(Memory::withTrashed()
            ->where('user_id', $user->id)
            ->with(['versions' => fn ($versions) => $versions->orderBy('version')])
            ->orderByRaw('path collate "C"')
            ->get()
            ->map(fn (Memory $memory): array => [
                'path' => $memory->path,
                'removed' => $memory->trashed(),
                'versions' => array_values($memory->versions
                    ->map(fn (MemoryVersion $version): array => $version->only('version', 'course_id', 'acting_user_id', 'content', 'created_at'))
                    ->all()),
            ])
            ->all());
    }

    /**
     * Hide the documents whose owners a removal has just hidden (spec
     * 16.2.2): its courses' own documents, and the working knowledge of its
     * subtopics. A document already removed keeps its own removal (16.2.3).
     *
     * Crosses students, because a subtopic's removal does. Only `Removals`
     * calls it, after stamping the owners.
     */
    public function remove(Removal $removal): void
    {
        $owners = fn (string $model) => $model::onlyTrashed()->where('removal_id', $removal->id)->select('id');

        Memory::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('owner_type', Course::class)->whereIn('owner_id', $owners(Course::class)))
                ->orWhere(fn (Builder $query) => $query->where('owner_type', Subtopic::class)->whereIn('owner_id', $owners(Subtopic::class))))
            ->update(['deleted_at' => now(), 'removal_id' => $removal->id]);
    }

    /**
     * Bring back exactly the documents one removal hid (spec 16.2.3).
     */
    public function restore(Removal $removal): void
    {
        Memory::onlyTrashed()
            ->where('removal_id', $removal->id)
            ->update(['deleted_at' => null, 'removal_id' => null]);
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
