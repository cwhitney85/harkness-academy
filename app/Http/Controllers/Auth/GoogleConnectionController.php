<?php

namespace App\Http\Controllers\Auth;

use App\Auth\GoogleIdentity;
use App\Auth\GoogleSignIn;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connect Google (spec 18.3.3, ledger D73) -- a Google sign-in whose email
 * already has an account, asked once for that account's password.
 *
 * The app has never verified an email, so anyone with the invitation code can
 * register someone else's address. Joining on the email alone would hand that
 * person's Google sign-in to whoever registered it; the password is what
 * proves the account is theirs.
 *
 * Pre:  the session holds what Google said (18.2.4), and its email belongs to
 *       a live account with no connection. Without it there is nothing to
 *       connect, and the visitor is sent to sign in.
 * Post: the right password connects the two and signs in as 18.3.2 does; a
 *       wrong one creates nothing.
 */
class GoogleConnectionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $identity = GoogleIdentity::in($request->session());

        if ($identity === null) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/connect-google', ['email' => $identity->email]);
    }

    public function store(Request $request): RedirectResponse
    {
        $identity = GoogleIdentity::in($request->session());
        $account = $identity === null ? null : User::query()->where('email', $identity->email)->first();

        if ($account === null) {
            return redirect()->route('login');
        }

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->string('password'), $account->password)) {
            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        $account->googleAccount()->create(['sub' => $identity->sub]);

        return GoogleSignIn::complete($request, $account);
    }
}
