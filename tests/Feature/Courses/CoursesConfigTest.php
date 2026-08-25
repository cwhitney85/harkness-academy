<?php

namespace Tests\Feature\Courses;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.6.1 -- the default plan is written down three times, on purpose.
 *
 * `config/courses.php` is the source of truth, but a schema default is baked
 * in at migrate time and a fresh Eloquent model never reads back what Postgres
 * filled in. Both copies exist for reasons the other cannot cover, so the only
 * defence against drift is this test (ledger D11).
 */
class CoursesConfigTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_model_default_matches_the_configured_default_plan(): void
    {
        $this->assertSame(config('courses.default_plan'), (new User)->plan);
    }

    #[Test]
    public function the_schema_default_matches_the_configured_default_plan(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'plan'));

        $default = DB::scalar(<<<'SQL'
            SELECT column_default FROM information_schema.columns
            WHERE table_name = 'users' AND column_name = 'plan'
        SQL);

        // Postgres reports it as `'standard'::character varying`.
        $this->assertSame(1, preg_match("/^'([^']*)'/", (string) $default, $matches));
        $this->assertSame(config('courses.default_plan'), $matches[1]);
    }

    #[Test]
    public function a_new_student_is_on_a_configured_plan(): void
    {
        $this->assertContains(User::factory()->create()->plan, config('courses.plans'));
    }

    #[Test]
    public function the_default_plan_is_one_of_the_configured_plans(): void
    {
        $this->assertContains(config('courses.default_plan'), config('courses.plans'));
    }

    #[Test]
    public function the_revision_cap_leaves_room_for_a_first_generation(): void
    {
        // Generation 1 is the original draft, so the cap is a count of
        // revisions on top of it (spec 4.5.4).
        $this->assertGreaterThanOrEqual(0, config('courses.max_syllabus_revisions'));
    }
}
