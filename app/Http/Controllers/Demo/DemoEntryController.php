<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S13 -- the demo's welcome page, the way in for a stranger with the
 * link (spec 12.1).
 *
 * GET says what the demo is and writes nothing, so a link preview or a crawler
 * following the URL mints no account. POST is the press of "Start the demo": it
 * mints an ordinary user row on the `demo` plan, signs it in and lands on
 * course creation. Past this point nothing knows the row is a demo except
 * syllabus depth and the marker in the header (ledger D50).
 *
 * The page is shown to everyone who opens the link, signed in or not: the link
 * is a promise of this page, and skipping it for a browser that happens to
 * hold a session is how the first cut sent a returning visitor straight to S2.
 *
 * `plan` is not fillable, so this `forceFill` is the only way a row reaches
 * `demo` (12.1.3).
 *
 * Pre:  none.
 * Post: GET renders the welcome page and writes nothing. POST ends whatever
 *       session the browser held, and leaves it signed in as a new
 *       `demo`-plan row on `/courses/create`.
 */
class DemoEntryController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('demo');
    }

    public function store(Request $request): RedirectResponse
    {
        // "Start the demo" starts one: a guest from an earlier visit is left
        // for the sweep (12.5.1), and a full account is signed out, not changed.
        Auth::logout();
        $request->session()->invalidate();

        // The email and password satisfy NOT NULL and UNIQUE (12.1.2).
        // `.invalid` is reserved (RFC 2606), so no mail can ever leave for it;
        // the password is never shown, because conversion sets its own.
        $visitor = (new User)->forceFill([
            'name' => 'Guest',
            'email' => 'demo-'.Str::uuid().'@harkness.invalid',
            'password' => Str::random(64),
            'plan' => 'demo',
            'has_chosen_password' => false,
        ]);
        $visitor->save();

        Auth::login($visitor);
        $request->session()->regenerate();

        return redirect()->route('courses.create');
    }
}
