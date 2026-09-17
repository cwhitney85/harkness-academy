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
    public function a_demo_visitor_following_the_link_again_carries_on(): void
    {
        $visitor = User::factory()->create(['plan' => 'demo']);

        $this->actingAs($visitor)->get(route('demo'))->assertRedirect(route('courses.create'));
        $this->post(route('demo.store'))->assertRedirect(route('courses.create'));

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function a_full_account_keeps_itself_and_goes_to_its_dashboard(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('demo'))->assertRedirect(route('dashboard'));
        $this->post(route('demo.store'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($student);
        $this->assertSame(1, User::query()->count());
    }
}
