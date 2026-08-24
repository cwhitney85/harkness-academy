<?php

namespace Tests\Feature\Courses;

use App\Courses\CourseStatus;
use App\Models\Course;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The database rules under spec 4.1 (ledger D7a/D7b).
 *
 * The exclusivity rule deliberately lives in an index rather than only in
 * application code, so these tests bypass `CreateCourse` entirely -- they are
 * asserting that the floor holds when the code above it is wrong.
 */
class CourseSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_database_refuses_a_second_open_course_in_one_subtopic(): void
    {
        $first = Course::factory()->create();

        $this->expectException(QueryException::class);

        Course::factory()->create([
            'user_id' => $first->user_id,
            'module_slug' => $first->module_slug,
            'subtopic_slug' => $first->subtopic_slug,
            'attempt' => 2,
        ]);
    }

    #[Test]
    public function the_database_allows_a_second_course_once_the_first_is_withdrawn(): void
    {
        $first = Course::factory()->withdrawn()->create();

        $second = Course::factory()->create([
            'user_id' => $first->user_id,
            'module_slug' => $first->module_slug,
            'subtopic_slug' => $first->subtopic_slug,
            'attempt' => 2,
        ]);

        $this->assertTrue($second->isOpen());
        $this->assertFalse($first->isOpen());
    }

    #[Test]
    public function the_database_allows_a_second_course_once_the_first_is_completed(): void
    {
        $first = Course::factory()->status(CourseStatus::Completed)->create();

        Course::factory()->create([
            'user_id' => $first->user_id,
            'module_slug' => $first->module_slug,
            'subtopic_slug' => $first->subtopic_slug,
            'attempt' => 2,
        ]);

        $this->assertSame(1, Course::query()->open()->count());
    }

    #[Test]
    public function the_database_refuses_a_repeated_attempt_number(): void
    {
        $first = Course::factory()->withdrawn()->create();

        $this->expectException(QueryException::class);

        Course::factory()->withdrawn()->create([
            'user_id' => $first->user_id,
            'module_slug' => $first->module_slug,
            'subtopic_slug' => $first->subtopic_slug,
            'attempt' => 1,
        ]);
    }

    /**
     * `CreateCourse` recognises a race by constraint name, so a rename would
     * silently turn a caught collision into an unhandled QueryException.
     */
    #[Test]
    public function the_indexes_carry_the_names_the_application_looks_for(): void
    {
        $this->assertSame(
            ['courses_one_attempt_per_level', 'courses_one_open_per_subtopic'],
            $this->indexNames(),
        );
    }

    /**
     * A partial index predicate cannot consult CourseStatus (ledger D7b), so
     * the terminal set is written into SQL by hand. This is what stops the two
     * definitions drifting apart.
     */
    #[Test]
    public function the_open_course_index_excludes_exactly_the_terminal_statuses(): void
    {
        $definition = DB::scalar(
            "select indexdef from pg_indexes where indexname = 'courses_one_open_per_subtopic'"
        );

        $this->assertStringContainsString('withdrawn_at IS NULL', (string) $definition);

        foreach (CourseStatus::cases() as $status) {
            $status->isTerminal()
                ? $this->assertStringContainsString(
                    "'{$status->value}'",
                    (string) $definition,
                    "terminal status [{$status->value}] is missing from the index predicate",
                )
                : $this->assertStringNotContainsString(
                    "'{$status->value}'",
                    (string) $definition,
                    "non-terminal status [{$status->value}] must not close the open slot",
                );
        }
    }

    /**
     * @return list<string>
     */
    private function indexNames(): array
    {
        return DB::table('pg_indexes')
            ->where('tablename', 'courses')
            ->where('indexname', '!=', 'courses_pkey')
            ->orderBy('indexname')
            ->pluck('indexname')
            ->all();
    }
}
