<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 18.4 -- a student who signed up with Google and never chose a
 * password confirms with Google, and may set one.
 */
class WithoutAPasswordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_confirm_screen_offers_google_only_to_a_connected_account(): void
    {
        $this->actingAs($this->googleOnly())->get(route('password.confirm'))
            ->assertInertia(fn ($page) => $page->where('canConfirmWithGoogle', true));

        $this->actingAs(User::factory()->create())->get(route('password.confirm'))
            ->assertInertia(fn ($page) => $page->where('canConfirmWithGoogle', false));
    }

    #[Test]
    public function the_accounts_own_google_account_confirms_the_password(): void
    {
        // 18.4.2
        $this->actingAs($this->googleOnly());
        $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));

        $this->google('sub-1')->assertRedirect(route('security.edit'));

        $this->assertNotNull(session('auth.password_confirmed_at'));
        $this->get(route('security.edit'))->assertOk();
    }

    #[Test]
    public function any_other_google_account_confirms_nothing(): void
    {
        // 18.4.2
        $this->actingAs($this->googleOnly());
        $this->get(route('security.edit'));

        $this->google('sub-2')
            ->assertRedirect(route('password.confirm'))
            ->assertSessionHasErrors('google');

        $this->assertNull(session('auth.password_confirmed_at'));
        $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function a_google_only_student_sets_a_password_and_signs_in_with_it(): void
    {
        // 18.4.3
        $student = $this->googleOnly();
        $this->actingAs($student);

        $this->get(route('security.edit'));
        $this->google('sub-1');

        $this->get(route('security.edit'))->assertInertia(fn ($page) => $page->where('hasChosenPassword', false));

        $this->post(route('user-password.store'), ['password' => 'chosen-password', 'password_confirmation' => 'chosen-password'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($student->refresh()->has_chosen_password);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $student->email, 'password' => 'chosen-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    #[Test]
    public function setting_a_password_needs_a_confirmation_and_no_password_of_its_own(): void
    {
        // 18.4.3: behind confirmation, not the current password.
        $student = $this->googleOnly();

        $this->actingAs($student)
            ->post(route('user-password.store'), ['password' => 'chosen-password', 'password_confirmation' => 'chosen-password'])
            ->assertRedirect(route('password.confirm'));

        $this->assertFalse($student->refresh()->has_chosen_password);
    }

    #[Test]
    public function an_account_that_chose_its_password_cannot_set_one_without_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('user-password.store'), ['password' => 'chosen-password', 'password_confirmation' => 'chosen-password'])
            ->assertForbidden();
    }

    #[Test]
    public function a_google_only_student_deletes_the_account_after_confirming_with_google(): void
    {
        // 18.4.5
        $student = $this->googleOnly();
        $this->actingAs($student);

        $this->from(route('profile.edit'))->delete(route('profile.destroy'))->assertRedirect(route('password.confirm'));
        $this->assertFalse($student->fresh()->trashed());

        $this->google('sub-1')->assertRedirect(route('profile.edit'));

        $this->delete(route('profile.destroy'))->assertRedirect('/');
        $this->assertTrue($student->fresh()->trashed());
        $this->assertGuest();
    }

    #[Test]
    public function an_account_with_its_own_password_still_types_it_to_delete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('profile.destroy'))
            ->assertSessionHasErrors('password');

        $this->assertFalse($user->fresh()->trashed());
    }

    #[Test]
    public function a_reset_marks_the_password_chosen(): void
    {
        // 18.4.1: the reset way of setting one.
        Notification::fake();
        $student = $this->googleOnly();

        $this->post(route('password.email'), ['email' => $student->email]);

        Notification::assertSentTo($student, ResetPassword::class, function (ResetPassword $notification) use ($student) {
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $student->email,
                'password' => 'chosen-password',
                'password_confirmation' => 'chosen-password',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue($student->refresh()->has_chosen_password);
    }

    private function googleOnly(): User
    {
        $student = User::factory()->create(['has_chosen_password' => false]);
        $student->googleAccount()->create(['sub' => 'sub-1']);

        return $student;
    }

    private function google(string $sub): TestResponse
    {
        Socialite::fake('google', GoogleUser::fake(['id' => $sub, 'email' => 'someone@example.com', 'name' => 'Someone']));

        return $this->get(route('google.callback'));
    }
}
