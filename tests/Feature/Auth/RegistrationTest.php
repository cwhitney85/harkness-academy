<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'harkness-invite-code';

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());

        config(['auth.invitation_code' => self::CODE]);
    }

    /**
     * A registration submission, overridable field by field.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'tos_accepted' => true,
            'invitation_code' => self::CODE,
            ...$overrides,
        ];
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), $this->submission());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_records_when_the_terms_were_accepted()
    {
        $this->post(route('register.store'), $this->submission());

        $this->assertNotNull(User::firstWhere('email', 'test@example.com')->tos_accepted_at);
    }

    public function test_registration_is_blocked_without_the_age_attestation()
    {
        $response = $this->from(route('register'))->post(route('register.store'), $this->submission([
            'tos_accepted' => null,
        ]));

        $response->assertSessionHasErrors('tos_accepted');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_when_the_age_attestation_is_declined()
    {
        $response = $this->from(route('register'))->post(route('register.store'), $this->submission([
            'tos_accepted' => false,
        ]));

        $response->assertSessionHasErrors('tos_accepted');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_without_an_invitation_code()
    {
        $response = $this->from(route('register'))->post(route('register.store'), $this->submission([
            'invitation_code' => null,
        ]));

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_by_the_wrong_invitation_code()
    {
        $response = $this->from(route('register'))->post(route('register.store'), $this->submission([
            'invitation_code' => 'not-the-code',
        ]));

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_is_blocked_when_no_invitation_code_is_configured()
    {
        config(['auth.invitation_code' => null]);

        $response = $this->from(route('register'))->post(route('register.store'), $this->submission());

        $response->assertSessionHasErrors('invitation_code');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_invited_account_is_on_the_standard_plan()
    {
        $this->post(route('register.store'), $this->submission());

        $this->assertSame('standard', User::firstWhere('email', 'test@example.com')->plan);
    }
}
