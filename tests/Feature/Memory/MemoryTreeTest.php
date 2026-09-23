<?php

namespace Tests\Feature\Memory;

use App\Memory\Exceptions\InvalidMemoryPath;
use App\Memory\MemoryTree;
use App\Models\Course;
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
        $course = $this->course();
        $user = $course->student;

        $this->tree()->put($course, self::WK, '# Solid: fractions');

        $this->assertSame('# Solid: fractions', $this->tree()->get($user, self::WK));

        $version = MemoryVersion::sole();

        $this->assertSame(1, $version->version);
        $this->assertSame('# Solid: fractions', $version->content);
        $this->assertSame(Memory::sole()->id, $version->memory_id);
    }

    #[Test]
    public function an_overwrite_replaces_the_document_and_keeps_the_prior_version(): void
    {
        $course = $this->course();
        $user = $course->student;

        $this->tree()->put($course, self::WK, 'first');
        $this->tree()->put($course, self::WK, 'second');

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
        [$colin, $someone] = [$this->course(), $this->course()];

        $this->tree()->put($colin, self::WK, 'mine');

        $this->assertNull($this->tree()->get($someone->student, self::WK));
        $this->assertSame([], $this->tree()->list($someone->student));

        $this->tree()->put($someone, self::WK, 'theirs');

        $this->assertSame('mine', $this->tree()->get($colin->student, self::WK));
        $this->assertSame(2, Memory::query()->count());
    }

    #[Test]
    public function listing_returns_the_paths_under_a_prefix_in_path_order(): void
    {
        $algebra = $this->course();
        $user = $algebra->student;
        $criteria = $algebra->materialsPrefix().'/COURSE_CRITERIA.md';

        $this->tree()->put($algebra, self::WK, 'x');
        $this->tree()->put($algebra, $criteria, 'x');
        $this->tree()->put($this->course($user, 'math', 'geometry'), 'math/geometry/WORKING_KNOWLEDGE.md', 'x');
        $this->tree()->put($this->course($user, 'language', 'spanish'), 'language/spanish/WORKING_KNOWLEDGE.md', 'x');

        $this->assertSame([
            'language/spanish/WORKING_KNOWLEDGE.md',
            $criteria,
            'math/algebra/WORKING_KNOWLEDGE.md',
            'math/geometry/WORKING_KNOWLEDGE.md',
        ], $this->tree()->list($user));

        $this->assertSame([
            $criteria,
            'math/algebra/WORKING_KNOWLEDGE.md',
        ], $this->tree()->list($user, 'math/algebra'));
    }

    #[Test]
    public function a_prefix_matches_whole_segments_only(): void
    {
        $in = $this->course();
        $out = $this->course($in->student, 'mathematics');

        $this->tree()->put($in, 'math/algebra/WORKING_KNOWLEDGE.md', 'in');
        $this->tree()->put($out, 'mathematics/algebra/WORKING_KNOWLEDGE.md', 'out');

        $this->assertSame(['math/algebra/WORKING_KNOWLEDGE.md'], $this->tree()->list($in->student, 'math'));
    }

    #[Test]
    public function listing_keeps_a_directorys_documents_contiguous(): void
    {
        $course = $this->course();
        $paths = array_map(fn (string $path) => $course->materialsPrefix().'/'.$path, ['a/x.md', 'a/z.md', 'ay.md']);

        // Under a collation that weights punctuation loosely, `ay.md` can land
        // between the two documents inside `a/` and break the tree render the
        // inspector (3.4) builds from this order.
        foreach ($paths as $path) {
            $this->tree()->put($course, $path, 'x');
        }

        $this->assertSame($paths, $this->tree()->list($course->student));
    }

    #[Test]
    public function an_underscore_in_a_prefix_is_not_a_wildcard(): void
    {
        $course = $this->course();
        $folder = $course->materialsPrefix();

        $this->tree()->put($course, "{$folder}/wk_1/notes.md", 'in');
        $this->tree()->put($course, "{$folder}/wkX1/notes.md", 'out');

        $this->assertSame(["{$folder}/wk_1/notes.md"], $this->tree()->list($course->student, "{$folder}/wk_1"));
    }

    #[Test]
    public function a_prefix_naming_one_document_lists_that_document(): void
    {
        $course = $this->course();

        $this->tree()->put($course, self::WK, 'x');

        $this->assertSame([self::WK], $this->tree()->list($course->student, self::WK));
    }

    #[Test]
    public function working_knowledge_belongs_to_the_subtopic_and_each_version_names_the_course_that_wrote_it(): void
    {
        $intro = $this->course();
        $this->tree()->put($intro, self::WK, 'after attempt 1');

        $intro->forceFill(['withdrawn_at' => now()])->save();
        $next = Course::factory()->for($intro->student, 'student')->create(['attempt' => 2]);
        $this->tree()->put($next, self::WK, 'after attempt 2');

        $memory = Memory::sole();
        $this->assertTrue($memory->owner->is($intro->subtopic));
        $this->assertSame(
            [1 => $intro->id, 2 => $next->id],
            MemoryVersion::query()->orderBy('version')->pluck('course_id', 'version')->all(),
        );
    }

    #[Test]
    public function a_courses_own_document_belongs_to_the_course(): void
    {
        $course = $this->course();

        $this->tree()->put($course, $course->materialsPrefix().'/COURSE_CRITERIA.md', 'x');

        $this->assertTrue(Memory::sole()->owner->is($course));
        $this->assertSame($course->id, MemoryVersion::sole()->course_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pathsNoCourseOwns(): array
    {
        return [
            'another subtopic\'s knowledge' => ['math/geometry/WORKING_KNOWLEDGE.md'],
            'another level\'s folder' => ['math/algebra/201/COURSE_CRITERIA.md'],
            'the subtopic folder itself' => ['math/algebra/notes.md'],
            'the module rollup' => ['math/WORKING_KNOWLEDGE.md'],
        ];
    }

    #[Test]
    #[DataProvider('pathsNoCourseOwns')]
    public function a_course_cannot_write_outside_its_own_folder_and_its_subtopics_knowledge(string $path): void
    {
        $this->expectException(InvalidMemoryPath::class);

        try {
            $this->tree()->put($this->course(), $path, 'x');
        } finally {
            $this->assertSame(0, Memory::query()->count());
        }
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
        $course = $this->course();

        $this->expectException(InvalidMemoryPath::class);

        try {
            $this->tree()->put($course, 'math/../secrets.md', 'x');
        } finally {
            $this->assertSame(0, Memory::query()->count());
        }
    }

    #[Test]
    public function a_document_read_names_the_version_that_holds_it(): void
    {
        $course = $this->course();
        $user = $course->student;

        $this->tree()->put($course, self::WK, 'first');
        $this->tree()->put($course, self::WK, 'second');

        $document = $this->tree()->document($user, self::WK);

        $this->assertNotNull($document);
        $this->assertSame(self::WK, $document->path);
        $this->assertSame('second', $document->content);

        // The pin must survive later writes: that is the whole point of
        // recording it (ledger D10.1).
        $this->tree()->put($course, self::WK, 'third');

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

        $this->tree()->put($this->course(), self::WK, 'theirs');

        $this->assertNull($this->tree()->document($mine, self::WK));
    }

    private function tree(): MemoryTree
    {
        return app(MemoryTree::class);
    }

    private function course(?User $student = null, string $module = 'math', string $subtopic = 'algebra'): Course
    {
        return Course::factory()->create([
            'user_id' => $student ?? User::factory(),
            'module_slug' => $module,
            'subtopic_slug' => $subtopic,
            'subtopic_title' => ucfirst($subtopic),
        ]);
    }
}
