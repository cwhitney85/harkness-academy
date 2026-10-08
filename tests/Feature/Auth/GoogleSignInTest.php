<?php

namespace Tests\Feature\Auth;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 18.2 and 18.3 -- signing in and signing up with Google.
 */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private const CODE = 'harkness-invite-code';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.invitation_code' => self::CODE]);
    }

    #[Test]
    public function google_is_asked_only_for_who_the_person_is(): void
    {
        // 18.2.1
        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);

        $location = $this->get(route('google.redirect'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertEqualsCanonicalizing(['openid', 'email', 'profile'], explode(' ', $query['scope']));
        $this->assertSame(route('google.callback'), $query['redirect_uri']);
    }

    #[Test]
    public function the_privacy_policy_google_links_to_renders_signed_out(): void
    {
        // 18.2.5
        $this->get(route('privacy'))->assertOk()->assertInertia(fn ($page) => $page->component('privacy'));
        $this->assertGuest();
    }

    #[Test]
    public function sign_in_and_register_reach_the_same_route(): void
    {
        // 18.3.1: both screens render the one button, whose link is the route.
        foreach (['login', 'register'] as $screen) {
            $this->get(route($screen))->assertOk();
        }

        $this->assertSame(1, substr_count((string) file_get_contents(resource_path('js/pages/auth/login.tsx')), '<GoogleButton'));
        $this->assertSame(1, substr_count((string) file_get_contents(resource_path('js/pages/auth/register.tsx')), '<GoogleButton'));
        $this->assertStringContainsString("from '@/routes/google'", (string) file_get_contents(resource_path('js/components/google-button.tsx')));
    }

    #[Test]
    public function a_connection_is_one_to_one_and_adds_nothing_nullable_to_users(): void
    {
        // 18.2.2
        $this->assertFalse(Schema::hasColumn('users', 'google_sub'));
        $this->assertFalse(collect(Schema::getColumns('users'))->firstWhere('name', 'has_chosen_password')['nullable']);

        [$user, $other] = User::factory()->count(2)->create();
        $user->googleAccount()->create(['sub' => 'sub-1']);

        // Each in a savepoint, so the first violation leaves the test's
        // transaction usable for the second.
        $this->assertThrows(fn () => DB::transaction(fn () => GoogleAccount::query()->create(['user_id' => $user->id, 'sub' => 'sub-2'])), UniqueConstraintViolationException::class);
        $this->assertThrows(fn () => DB::transaction(fn () => GoogleAccount::query()->create(['user_id' => $other->id, 'sub' => 'sub-1'])), UniqueConstraintViolationException::class);
    }

    #[Test]
    public function a_known_sub_signs_in_even_when_googles_email_has_changed(): void
    {
        // 18.3.2, 18.2.3
        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->googleAccount()->create(['sub' => 'sub-1']);

        $this->google('sub-1', 'new@example.com')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function an_account_with_two_factor_stops_at_the_challenge(): void
    {
        // 18.3.2.1
        $user = User::factory()->withTwoFactor()->create();
        $user->googleAccount()->create(['sub' => 'sub-1']);

        $this->google('sub-1', $user->email)->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        // Leaving the challenge signs nobody in.
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function a_deleted_account_is_refused_as_password_sign_in_refuses_it(): void
    {
        // 18.3.2.2
        $user = User::factory()->create();
        $user->googleAccount()->create(['sub' => 'sub-1']);
        $user->delete();

        $this->google('sub-1', $user->email)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    #[Test]
    public function an_unknown_sub_with_a_known_email_connects_only_with_the_password(): void
    {
        // 18.3.3
        $user = User::factory()->create(['email' => 'student@example.com']);

        $this->google('sub-1', 'Student@Example.com')->assertRedirect(route('google.connect'));
        $this->get(route('google.connect'))->assertOk();

        $this->post(route('google.connect.store'), ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertGuest();
        $this->assertSame(0, GoogleAccount::query()->count());

        $this->post(route('google.connect.store'), ['password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('sub-1', $user->googleAccount->sub);
    }

    #[Test]
    public function connecting_an_account_with_two_factor_stops_at_the_challenge(): void
    {
        // 18.3.3 then 18.3.2.1
        $user = User::factory()->withTwoFactor()->create();

        $this->google('sub-1', $user->email);
        $this->post(route('google.connect.store'), ['password' => 'password'])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    #[Test]
    public function an_unknown_sub_whose_email_belongs_to_a_deleted_account_is_refused(): void
    {
        // 18.3.3.1
        $user = User::factory()->create();
        $user->delete();

        $this->google('sub-1', $user->email)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->post(route('google.connect.store'), ['password' => 'password'])->assertRedirect(route('login'));
        $this->assertSame(0, GoogleAccount::query()->count());
    }

    #[Test]
    public function an_account_already_connected_to_another_google_account_is_not_connected_again(): void
    {
        $user = User::factory()->create();
        $user->googleAccount()->create(['sub' => 'sub-1']);

        $this->google('sub-2', $user->email)->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertSame(1, GoogleAccount::query()->count());
    }

    #[Test]
    public function a_stranger_finishes_signing_up_with_the_code_and_the_attestation(): void
    {
        // 18.3.4
        $this->google('sub-1', 'new@example.com', 'New Student')->assertRedirect(route('google.sign-up'));

        $props = $this->inertiaProps($this->get(route('google.sign-up')));
        $this->assertSame(['New Student', 'new@example.com', false], [$props['name'], $props['email'], $props['keeping']]);

        foreach ([['invitation_code' => ''], ['invitation_code' => 'not-the-code'], ['tos_accepted' => false]] as $override) {
            $this->post(route('google.sign-up.store'), $this->submission($override))
                ->assertSessionHasErrors(array_key_first($override));
        }
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, GoogleAccount::query()->count());

        $this->post(route('google.sign-up.store'), $this->submission(['name' => 'Renamed']))
            ->assertRedirect(route('dashboard'));

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(['Renamed', 'new@example.com', 'standard', false], [$user->name, $user->email, $user->plan, $user->has_chosen_password]);
        $this->assertNotNull($user->tos_accepted_at);
        $this->assertSame('sub-1', GoogleAccount::query()->sole()->sub);
    }

    #[Test]
    public function a_post_naming_a_sub_google_did_not_send_creates_nothing(): void
    {
        // 18.2.4: with nothing from Google in the session, there is nothing to act on.
        $this->post(route('google.sign-up.store'), $this->submission(['sub' => 'forged', 'email' => 'forged@example.com']))
            ->assertRedirect(route('login'));
        $this->post(route('google.connect.store'), ['password' => 'password', 'sub' => 'forged'])
            ->assertRedirect(route('login'));

        // And with something there, a form field cannot replace it.
        $this->google('sub-1', 'new@example.com');
        $this->post(route('google.sign-up.store'), $this->submission(['sub' => 'forged', 'email' => 'forged@example.com']));

        $this->assertSame('new@example.com', User::query()->sole()->email);
        $this->assertSame('sub-1', GoogleAccount::query()->sole()->sub);
    }

    #[Test]
    public function cancelling_at_google_goes_back_to_sign_in(): void
    {
        $this->get(route('google.callback', ['error' => 'access_denied']))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function google(string $sub, string $email, string $name = 'Google Name'): TestResponse
    {
        Socialite::fake('google', GoogleUser::fake(['id' => $sub, 'email' => $email, 'name' => $name]));

        return $this->get(route('google.callback'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return [
            'name' => 'New Student',
            'tos_accepted' => true,
            'invitation_code' => self::CODE,
            ...$overrides,
        ];
    }
}
