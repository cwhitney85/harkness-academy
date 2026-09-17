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
 * `plan` is not fillable, so this `forceFill` is the only way a row reaches
 * `demo` (12.1.3).
 *
 * Pre:  none.
 * Post: GET renders the welcome page to a signed-out visitor and writes
 *       nothing. POST leaves a signed-out visitor signed in as a new
 *       `demo`-plan row on `/courses/create`. A visitor already signed in is
 *       sent on by either verb and keeps the account they have.
 */
class DemoEntryController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        return $request->user() instanceof User
            ? $this->onward($request->user())
            : Inertia::render('demo');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user() instanceof User) {
            return $this->onward($request->user());
        }

        // The email and password satisfy NOT NULL and UNIQUE (12.1.2).
        // `.invalid` is reserved (RFC 2606), so no mail can ever leave for it;
        // the password is never shown, because conversion sets its own.
        $visitor = (new User)->forceFill([
            'name' => 'Guest',
            'email' => 'demo-'.Str::uuid().'@harkness.invalid',
            'password' => Str::random(64),
            'plan' => 'demo',
        ]);
        $visitor->save();

        Auth::login($visitor);
        $request->session()->regenerate();

        return redirect()->route('courses.create');
    }

    /**
     * A demo visitor who follows the link again carries on with the demo; a
     * full account has no demo to start.
     */
    private function onward(User $user): RedirectResponse
    {
        return redirect()->route($user->isDemo() ? 'courses.create' : 'dashboard');
    }
}
