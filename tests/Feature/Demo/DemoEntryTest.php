<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 12.1 -- the demo entry.
 */
class DemoEntryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function opening_the_link_shows_the_welcome_page_and_makes_no_account(): void
    {
        $this->get(route('demo'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('demo'));

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function starting_the_demo_lands_on_course_creation_as_a_new_demo_account(): void
    {
        $this->post(route('demo.store'))->assertRedirect(route('courses.create'));

        $visitor = User::sole();

        $this->assertAuthenticatedAs($visitor);
        $this->assertSame('demo', $visitor->plan);
        // Nothing was attested (12.1.5).
        $this->assertNull($visitor->tos_accepted_at);
    }

    #[Test]
    public function two_starts_make_two_accounts(): void
    {
        $this->post(route('demo.store'));
        $this->post(route('logout'));
        $this->post(route('demo.store'));

        $this->assertSame(2, User::query()->where('plan', 'demo')->count());
        $this->assertSame(2, User::query()->distinct()->count('email'));
    }

    #[Test]
    public function a_browser_already_in_a_demo_sees_the_welcome_page_and_starting_again_starts_fresh(): void
    {
        $earlier = User::factory()->create(['plan' => 'demo']);

        $this->actingAs($earlier)
            ->get(route('demo'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('demo'));

        $this->post(route('demo.store'))->assertRedirect(route('courses.create'));

        $this->assertSame(2, User::query()->where('plan', 'demo')->count());
        $this->assertNotSame($earlier->id, auth()->id());
    }

    #[Test]
    public function a_full_account_sees_the_welcome_page_and_is_signed_out_not_changed_by_starting(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('demo'))->assertOk();
        $this->post(route('demo.store'))->assertRedirect(route('courses.create'));

        $this->assertSame('standard', $student->fresh()->plan);
        $this->assertNotSame($student->id, auth()->id());
    }
}
