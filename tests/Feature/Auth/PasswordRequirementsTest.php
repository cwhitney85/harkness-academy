<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Spec 1.5 -- a student learns the password rules from the screen, not one
 * validation error at a time.
 */
class PasswordRequirementsTest extends TestCase
{
    use RefreshDatabase;

    /** The rule production applies, so the lines under test are the real ones. */
    protected function setUp(): void
    {
        parent::setUp();

        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols(),
        );
    }

    /** @return list<string> */
    private function expectedRequirements(): array
    {
        return [
            'At least 12 characters',
            'An uppercase letter and a lowercase letter',
            'At least one number',
            'At least one symbol',
        ];
    }

    public function test_the_register_screen_states_every_rule()
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $this->get(route('register'))->assertInertia(
            fn (Assert $page) => $page
                ->where('passwordRequirements', $this->expectedRequirements())
        );
    }

    public function test_the_reset_password_screen_states_every_rule()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $this->get(route('password.reset', $notification->token))->assertInertia(
                fn (Assert $page) => $page
                    ->where('passwordRequirements', $this->expectedRequirements())
            );

            return true;
        });
    }

    public function test_the_security_screen_states_every_rule()
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('passwordRequirements', $this->expectedRequirements())
            );
    }

    public function test_a_submission_failing_on_two_rules_names_both()
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->from(route('register'))
            ->followingRedirects()
            ->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'shortpass',
                'password_confirmation' => 'shortpass',
                'tos_accepted' => true,
            ]);

        $response->assertInertia(fn (Assert $page) => $page
            ->where('errors.password', fn (string $message) => str_contains($message, 'at least 12 characters')
                && str_contains($message, 'at least one symbol'))
        );
    }

    /** A rule the app does not apply contributes no line. */
    public function test_the_lines_follow_the_rule_the_validator_applies()
    {
        Password::defaults(fn () => Password::min(8)->numbers());

        $this->skipUnlessFortifyHas(Features::registration());

        $this->get(route('register'))->assertInertia(
            fn (Assert $page) => $page
                ->where('passwordRequirements', ['At least 8 characters', 'At least one number'])
        );
    }
}
