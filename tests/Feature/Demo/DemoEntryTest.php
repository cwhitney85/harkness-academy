<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 12.1 -- the demo entry.
 */
class DemoEntryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_visitor_with_no_session_lands_on_course_creation_as_a_new_demo_account(): void
    {
        $this->get(route('demo'))->assertRedirect(route('courses.create'));

        $visitor = User::sole();

        $this->assertAuthenticatedAs($visitor);
        $this->assertSame('demo', $visitor->plan);
        // 12.1.5: nothing was attested.
        $this->assertNull($visitor->tos_accepted_at);
    }

    #[Test]
    public function two_visits_make_two_accounts(): void
    {
        $this->get(route('demo'));
        $this->post(route('logout'));
        $this->get(route('demo'));

        $this->assertSame(2, User::query()->where('plan', 'demo')->count());
        $this->assertSame(2, User::query()->distinct()->count('email'));
    }

    #[Test]
    public function a_signed_in_student_keeps_their_account(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('demo'))->assertRedirect(route('courses.create'));

        $this->assertAuthenticatedAs($student);
        $this->assertSame(1, User::query()->count());
    }
}
