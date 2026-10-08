<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/**
 * The end of a Google sign-in, once the account is known (spec 18.3.2).
 *
 * Fortify's two-factor challenge is a step of its password sign-in, so a
 * sign-in that does not go through `POST /login` has to send itself there
 * (ledger D75). These are the two steps of that pipeline a Google sign-in
 * needs; the rest are about checking a password.
 */
final class GoogleSignIn
{
    /**
     * Pre:  the account was found by its connection's `sub`, or connected
     *       this request.
     * Post: a deleted account is refused, signing nobody in (18.3.2.2); one
     *       with two-factor on is at the challenge, still signed out, exactly
     *       as a password sign-in leaves it (18.3.2.1); anyone else is signed
     *       in, the session regenerated.
     */
    public static function complete(Request $request, User $user): RedirectResponse
    {
        GoogleIdentity::forget($request->session());

        if ($user->trashed()) {
            return self::refuse();
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * A deleted account is refused exactly as password sign-in refuses it
     * (18.3.2.2, 18.3.3.1, spec 16.2.10).
     */
    public static function refuse(): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => __('auth.failed')]);
    }
}
