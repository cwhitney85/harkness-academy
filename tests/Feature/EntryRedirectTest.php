<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.13.2 -- the entry URL decides its own audience.
 *
 * `GET /` used to sit inside the auth group, which made both arms the
 * middleware's: a student reached their dashboard and a guest was sent to sign
 * in. 8.13 gave the guest a page, so the decision moved into the route and
 * this holds both arms of it.
 */
class EntryRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_signed_in_student_lands_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function a_guest_gets_the_landing_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page->component('landing'));
    }
}
