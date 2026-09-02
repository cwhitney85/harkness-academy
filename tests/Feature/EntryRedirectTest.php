<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 8.6 -- `home | entry --> S1`.
 *
 * The entry URL renders nothing of its own. It is one decision made at the
 * auth boundary, and this holds both of its arms: a student reaches their
 * dashboard, a guest reaches sign-in. It replaces the starter kit's
 * ExampleTest, which asserted that `/` returned 200 -- true only while a
 * welcome page stood between the entry and the edge the map draws.
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
    public function a_guest_is_sent_to_sign_in(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }
}
