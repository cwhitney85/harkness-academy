<?php

namespace Tests\Feature\Memory;

use App\Memory\Exceptions\InvalidMemoryPath;
use App\Memory\MemoryTree;
use App\Models\Memory;
use App\Models\MemoryVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 3.1/3.2 -- the tables, and the path API that is their only door.
 */
class MemoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private const WK = 'math/algebra/WORKING_KNOWLEDGE.md';

    #[Test]
    public function a_write_reads_back_and_records_a_version(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, self::WK, '# Solid: fractions');

        $this->assertSame('# Solid: fractions', $this->tree()->get($user, self::WK));

        $version = MemoryVersion::sole();

        $this->assertSame(1, $version->version);
        $this->assertSame('# Solid: fractions', $version->content);
        $this->assertSame(Memory::sole()->id, $version->memory_id);
    }

    #[Test]
    public function an_overwrite_replaces_the_document_and_keeps_the_prior_version(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, self::WK, 'first');
        $this->tree()->put($user, self::WK, 'second');

        $this->assertSame('second', $this->tree()->get($user, self::WK));

        $this->assertSame(1, Memory::query()->count());
        $this->assertSame(
            [1 => 'first', 2 => 'second'],
            MemoryVersion::query()->orderBy('version')->pluck('content', 'version')->all()
        );
    }

    #[Test]
    public function reading_a_path_that_was_never_written_returns_null(): void
    {
        $this->assertNull($this->tree()->get(User::factory()->create(), self::WK));
    }

    #[Test]
    public function one_users_document_is_invisible_to_another(): void
    {
        [$colin, $someone] = [User::factory()->create(), User::factory()->create()];

        $this->tree()->put($colin, self::WK, 'mine');

        $this->assertNull($this->tree()->get($someone, self::WK));
        $this->assertSame([], $this->tree()->list($someone));

        $this->tree()->put($someone, self::WK, 'theirs');

        $this->assertSame('mine', $this->tree()->get($colin, self::WK));
        $this->assertSame(2, Memory::query()->count());
    }

    #[Test]
    public function listing_returns_the_paths_under_a_prefix_in_path_order(): void
    {
        $user = User::factory()->create();

        foreach ([
            'math/algebra/WORKING_KNOWLEDGE.md',
            'math/algebra/101/COURSE_CRITERIA.md',
            'math/geometry/WORKING_KNOWLEDGE.md',
            'language/spanish/WORKING_KNOWLEDGE.md',
        ] as $path) {
            $this->tree()->put($user, $path, 'x');
        }

        $this->assertSame([
            'language/spanish/WORKING_KNOWLEDGE.md',
            'math/algebra/101/COURSE_CRITERIA.md',
            'math/algebra/WORKING_KNOWLEDGE.md',
            'math/geometry/WORKING_KNOWLEDGE.md',
        ], $this->tree()->list($user));

        $this->assertSame([
            'math/algebra/101/COURSE_CRITERIA.md',
            'math/algebra/WORKING_KNOWLEDGE.md',
        ], $this->tree()->list($user, 'math/algebra'));
    }

    #[Test]
    public function a_prefix_matches_whole_segments_only(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, 'math/algebra.md', 'in');
        $this->tree()->put($user, 'mathematics/algebra.md', 'out');

        $this->assertSame(['math/algebra.md'], $this->tree()->list($user, 'math'));
    }

    #[Test]
    public function listing_keeps_a_directorys_documents_contiguous(): void
    {
        $user = User::factory()->create();

        // Under a collation that weights punctuation loosely, `math/ay.md`
        // can land between the two documents inside `math/a` and break the
        // tree render the inspector (3.4) builds from this order.
        foreach (['math/a/x.md', 'math/a/z.md', 'math/ay.md'] as $path) {
            $this->tree()->put($user, $path, 'x');
        }

        $this->assertSame(['math/a/x.md', 'math/a/z.md', 'math/ay.md'], $this->tree()->list($user));
    }

    #[Test]
    public function an_underscore_in_a_prefix_is_not_a_wildcard(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, 'math/wk_1/notes.md', 'in');
        $this->tree()->put($user, 'math/wkX1/notes.md', 'out');

        $this->assertSame(['math/wk_1/notes.md'], $this->tree()->list($user, 'math/wk_1'));
    }

    #[Test]
    public function a_prefix_naming_one_document_lists_that_document(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, self::WK, 'x');

        $this->assertSame([self::WK], $this->tree()->list($user, self::WK));
    }

    /**
     * @return list<array{string}>
     */
    public static function invalidPaths(): array
    {
        return [
            'empty' => [''],
            'absolute' => ['/math/algebra.md'],
            'trailing slash' => ['math/algebra/'],
            'empty segment' => ['math//algebra.md'],
            'traversal' => ['math/../../etc/passwd'],
            'current directory' => ['math/./algebra.md'],
            'space' => ['math/my notes.md'],
            'leading dot' => ['math/.hidden.md'],
            'wildcard' => ['math/%.md'],
            'too long' => ['math/'.str_repeat('a', 256).'.md'],
        ];
    }

    #[Test]
    #[DataProvider('invalidPaths')]
    public function an_invalid_path_is_rejected_before_any_query(string $path): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidMemoryPath::class);

        $this->tree()->get($user, $path);
    }

    #[Test]
    public function an_invalid_path_is_rejected_on_write_too(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidMemoryPath::class);

        try {
            $this->tree()->put($user, 'math/../secrets.md', 'x');
        } finally {
            $this->assertSame(0, Memory::query()->count());
        }
    }

    #[Test]
    public function a_document_read_names_the_version_that_holds_it(): void
    {
        $user = User::factory()->create();

        $this->tree()->put($user, self::WK, 'first');
        $this->tree()->put($user, self::WK, 'second');

        $document = $this->tree()->document($user, self::WK);

        $this->assertNotNull($document);
        $this->assertSame(self::WK, $document->path);
        $this->assertSame('second', $document->content);

        // The pin must survive later writes: that is the whole point of
        // recording it (ledger D10.1).
        $this->tree()->put($user, self::WK, 'third');

        $pinned = MemoryVersion::query()->findOrFail($document->versionId);

        $this->assertSame('second', $pinned->content);
        $this->assertSame('third', $this->tree()->get($user, self::WK));
    }

    #[Test]
    public function a_document_read_of_an_empty_path_is_null(): void
    {
        $user = User::factory()->create();

        $this->assertNull($this->tree()->document($user, self::WK));
    }

    #[Test]
    public function a_document_read_cannot_see_another_students_file(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $this->tree()->put($theirs, self::WK, 'theirs');

        $this->assertNull($this->tree()->document($mine, self::WK));
    }

    private function tree(): MemoryTree
    {
        return app(MemoryTree::class);
    }
}
