<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * `GET /demo` -- screen S13, the way in for a stranger with the link (spec 12.1).
 *
 * It renders nothing. It mints an ordinary user row on the `demo` plan, signs
 * it in and lands on course creation; past this point nothing knows the row is
 * a demo except syllabus depth (ledger D50). No attestation and nothing to
 * press (12.1.5): a bare GET, whose cost -- a crawler can mint rows -- is what
 * the sweep in 12.5.1 answers.
 *
 * `plan` is not fillable, so this `forceFill` is the only way a row reaches
 * `demo` (12.1.3).
 *
 * Pre:  none.
 * Post: a signed-in visitor is on `/courses/create`. A visitor who arrived
 *       with no session is signed in as a new `demo`-plan row; one already
 *       signed in keeps the account they have.
 */
class DemoEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! Auth::check()) {
            // The email and password satisfy NOT NULL and UNIQUE (12.1.2).
            // `.invalid` is reserved (RFC 2606), so no mail can ever leave for
            // it; the password is never shown, because conversion sets its own.
            $visitor = (new User)->forceFill([
                'name' => 'Guest',
                'email' => 'demo-'.Str::uuid().'@harkness.invalid',
                'password' => Str::random(64),
                'plan' => 'demo',
            ]);
            $visitor->save();

            Auth::login($visitor);
            $request->session()->regenerate();
        }

        return redirect()->route('courses.create');
    }
}
