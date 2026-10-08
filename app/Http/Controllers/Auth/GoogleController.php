<?php

namespace App\Http\Controllers\Auth;

use App\Auth\GoogleIdentity;
use App\Auth\GoogleSignIn;
use App\Http\Controllers\Controller;
use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * The Google round-trip (spec 18.2, 18.3, 18.4.2, 18.5).
 *
 * One way out to Google and one way back, so Google's console carries one
 * redirect URI. Who is signed in when Google answers says what the trip was
 * for: a guest is signing in, a demo is keeping its course, and anyone else
 * is confirming it is them. Socialite's `state` ties the answer to the
 * session that asked.
 */
class GoogleController extends Controller
{
    /** What keeping a demo says when Google's person already has an account (18.5.2). */
    public const TAKEN = 'This email already has an account. Sign in instead.';

    /**
     * Socialite's Google driver asks for `openid`, `profile` and `email` and
     * nothing more, which is what 18.2.1 allows; GoogleSignInTest pins it.
     */
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->has('error')) {
            return $this->backToStart($user);
        }

        try {
            $identity = GoogleIdentity::from(Socialite::driver('google')->user());
        } catch (InvalidStateException) {
            return $this->backToStart($user);
        }

        return match (true) {
            $user === null => $this->signIn($request, $identity),
            $user->isDemo() => $this->keep($request, $identity),
            default => $this->confirm($request, $user, $identity),
        };
    }

    /**
     * Found by `sub`, never by email (18.2.3). An unknown `sub` whose email
     * has an account must prove it owns that account first (18.3.3); one
     * whose email has none signs up (18.3.4).
     */
    private function signIn(Request $request, GoogleIdentity $identity): RedirectResponse
    {
        $connection = GoogleAccount::query()->where('sub', $identity->sub)->first();

        if ($connection !== null) {
            return GoogleSignIn::complete($request, $connection->user);
        }

        $account = User::withTrashed()->where('email', $identity->email)->first();

        if ($account?->trashed()) {
            return GoogleSignIn::refuse();
        }

        // Connecting would give the account a second Google account (18.2.2).
        if ($account?->googleAccount !== null) {
            return redirect()->route('login')->withErrors(['email' => 'This account signs in with a different Google account.']);
        }

        $identity->keep($request->session());

        return redirect()->route($account === null ? 'google.sign-up' : 'google.connect');
    }

    /**
     * Keeping a demo is refused when Google's person already has an account,
     * by email or by connection, and the row is left as it was (18.5.2).
     */
    private function keep(Request $request, GoogleIdentity $identity): RedirectResponse
    {
        $taken = User::withTrashed()->where('email', $identity->email)->exists()
            || GoogleAccount::query()->where('sub', $identity->sub)->exists();

        if ($taken) {
            return redirect()->route('demo.account.create')->withErrors(['google' => self::TAKEN]);
        }

        $identity->keep($request->session());

        return redirect()->route('google.sign-up');
    }

    /**
     * Confirms the session's password, exactly as typing it would, when
     * Google returns this account's own `sub` -- and nothing otherwise
     * (18.4.2).
     */
    private function confirm(Request $request, User $user, GoogleIdentity $identity): RedirectResponse
    {
        if ($user->googleAccount?->sub !== $identity->sub) {
            return redirect()->route('password.confirm')->withErrors(['google' => 'That is not the Google account connected to this one.']);
        }

        $request->session()->passwordConfirmed();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Google sent back no answer this session can use: the student pressed
     * Cancel, or the trip was not this session's. They go back to where they
     * started.
     */
    private function backToStart(?User $user): RedirectResponse
    {
        return match (true) {
            $user === null => redirect()->route('login'),
            $user->isDemo() => redirect()->route('demo.account.create'),
            default => redirect()->route('password.confirm'),
        };
    }
}
