<?php

namespace Tests\Feature\Courses;

use App\Courses\SubtopicOrigin;
use App\Models\Course;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 16.1.1–16.1.4 -- one subtopics table for catalog and student subtopics
 * (ledger D59).
 */
class SubtopicsTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CourseCatalogSeeder::class);
    }

    #[Test]
    public function catalog_rows_read_catalog_and_listed_with_no_creator(): void
    {
        $this->assertSame(0, Subtopic::query()
            ->where(fn ($rows) => $rows
                ->where('origin', '!=', SubtopicOrigin::Catalog)
                ->orWhere('listed', false)
                ->orWhereNotNull('created_by'))
            ->count());
    }

    #[Test]
    public function a_student_row_must_name_its_creator_and_a_catalog_row_must_not(): void
    {
        $math = Module::query()->where('slug', 'math')->sole();
        $student = User::factory()->create();

        foreach ([
            ['origin' => 'student', 'created_by' => null],
            ['origin' => 'catalog', 'created_by' => $student->id],
            ['origin' => 'imported', 'created_by' => null],
        ] as $i => $row) {
            try {
                DB::transaction(fn () => DB::table('subtopics')->insert($row + [
                    'module_id' => $math->id, 'slug' => "row-{$i}", 'title' => 'Row', 'position' => 99, 'listed' => false,
                ]));
                $this->fail("Accepted {$row['origin']} with created_by ".var_export($row['created_by'], true));
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_row_that_does_not_say_what_it_is_is_refused(): void
    {
        $this->expectException(QueryException::class);

        DB::table('subtopics')->insert([
            'module_id' => Module::query()->where('slug', 'math')->sole()->id,
            'slug' => 'unsaid', 'title' => 'Unsaid', 'position' => 99,
        ]);
    }

    #[Test]
    public function two_students_typing_the_same_name_share_one_unlisted_student_row(): void
    {
        $this->fakeModel(fn (): array => ['verdict' => 'in_scope', 'module' => null, 'reason' => 'Fine.']);
        [$first, $second] = User::factory()->count(2)->create();

        $this->actingAs($first)->post(route('courses.store'), $this->typed('Knot Theory'))->assertRedirect();
        $this->actingAs($second)->post(route('courses.store'), $this->typed('knot  theory'))->assertRedirect();

        $row = Subtopic::query()->where('slug', 'knot-theory')->sole();
        $this->assertSame(SubtopicOrigin::Student, $row->origin);
        $this->assertFalse($row->listed);
        $this->assertSame($first->id, $row->created_by);
        $this->assertSame('Knot Theory', $row->title);
        $this->assertSame([$row->id, $row->id], Course::query()->orderBy('id')->pluck('subtopic_id')->all());
    }

    #[Test]
    public function typing_a_catalog_name_finds_the_catalog_row(): void
    {
        $algebra = Subtopic::query()->where('slug', 'algebra')->sole();
        $rows = Subtopic::query()->count();

        $this->actingAs(User::factory()->create())
            ->post(route('courses.store'), $this->typed('algebra'))
            ->assertRedirect();

        $this->assertSame($algebra->id, Course::query()->sole()->subtopic_id);
        $this->assertSame($rows, Subtopic::query()->count());
    }

    #[Test]
    public function the_backfill_points_every_course_at_a_row_and_prints_the_rows_it_made(): void
    {
        $migration = require database_path('migrations/2026_09_23_000001_add_subtopic_id_to_courses_table.php');
        $migration->down();

        [$first, $second] = User::factory()->count(2)->create();
        $course = fn (User $student, string $module, string $slug, string $title, string $at): int => DB::table('courses')->insertGetId([
            'user_id' => $student->id, 'module_slug' => $module, 'subtopic_slug' => $slug, 'subtopic_title' => $title,
            'skill_level' => 101, 'attempt' => 1, 'status' => 'created', 'withdrawn_at' => now(),
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $catalog = $course($first, 'math', 'algebra', 'Algebra', '2026-09-01');
        $later = $course($second, 'history', 'civil-war', 'Civil War', '2026-09-03');
        $earlier = $course($first, 'history', 'civil-war', 'civil war', '2026-09-02');

        ob_start();
        $migration->up();
        $printed = (string) ob_get_clean();

        $civilWar = Subtopic::query()->where('slug', 'civil-war')->sole();
        $this->assertSame(SubtopicOrigin::Student, $civilWar->origin);
        $this->assertFalse($civilWar->listed);
        $this->assertSame($first->id, $civilWar->created_by, 'Credited to whoever typed it first.');
        $this->assertSame('civil war', $civilWar->title);

        $this->assertSame(Subtopic::query()->where('slug', 'algebra')->sole()->id, Course::query()->find($catalog)->subtopic_id);
        $this->assertSame($civilWar->id, Course::query()->find($earlier)->subtopic_id);
        $this->assertSame($civilWar->id, Course::query()->find($later)->subtopic_id);

        $this->assertSame(1, substr_count($printed, 'student subtopic created'));
        $this->assertStringContainsString('civil-war «civil war», first typed by user #'.$first->id, $printed);
    }

    #[Test]
    public function a_module_with_courses_under_it_cannot_be_deleted(): void
    {
        Course::factory()->create();

        $this->expectException(QueryException::class);

        Module::query()->where('slug', 'math')->delete();
    }

    #[Test]
    public function the_picker_offers_only_listed_subtopics(): void
    {
        $student = User::factory()->create();
        $math = Module::query()->where('slug', 'math')->sole();
        $unlisted = Subtopic::findOrCreateFor($math, 'knot-theory', 'Knot Theory', $student);

        $this->actingAs($student)
            ->get(route('courses.create'))
            ->assertInertia(fn ($page) => $page->where(
                'modules.0.subtopics',
                fn ($subtopics): bool => collect($subtopics)->pluck('slug')->doesntContain('knot-theory')
                    && collect($subtopics)->pluck('slug')->contains('algebra'),
            ));

        $unlisted->update(['listed' => true]);

        $this->actingAs($student)
            ->get(route('courses.create'))
            ->assertInertia(fn ($page) => $page->where(
                'modules.0.subtopics',
                fn ($subtopics): bool => collect($subtopics)->pluck('slug')->contains('knot-theory'),
            ));
    }

    #[Test]
    public function an_unlisted_subtopic_cannot_be_started_through_the_picker(): void
    {
        $student = User::factory()->create();
        $unlisted = Subtopic::findOrCreateFor(Module::query()->where('slug', 'math')->sole(), 'knot-theory', 'Knot Theory', $student);

        $this->actingAs($student)
            ->post(route('courses.store'), [
                'source' => 'catalog', 'subtopic_id' => $unlisted->id, 'module_slug' => null, 'name' => null, 'level' => 101,
            ])
            ->assertSessionHasErrors('subtopic_id');

        $this->assertSame(0, Course::query()->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function typed(string $name): array
    {
        return ['source' => 'free_text', 'module_slug' => 'math', 'subtopic_id' => null, 'name' => $name, 'level' => 101];
    }
}
