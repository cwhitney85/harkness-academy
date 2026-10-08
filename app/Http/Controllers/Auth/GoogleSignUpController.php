<?php

namespace App\Http\Controllers\Auth;

use App\Auth\GoogleIdentity;
use App\Auth\GoogleSignIn;
use App\Concerns\InvitationValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finish signing up (spec 18.3.4, ledger D73) -- what Google cannot supply:
 * the invitation code and the 18-or-older attestation, checked by the rules
 * registration and demo conversion share (1.6, 12.4.3).
 *
 * Two people reach it. A guest new to the app gets a new `standard` account.
 * A demo keeping its course gets its own row moved to `standard`, nothing
 * copied (18.5.1, 12.4.2). Either way the password is one nobody was shown,
 * so `has_chosen_password` stays false (18.4.1), and the connection is made.
 *
 * Pre:  the session holds what Google said (18.2.4); the visitor is a guest
 *       or on the demo plan. Anyone else is sent on.
 * Post: on success, exactly one account and one connection; on a validation
 *       failure, neither -- a demo row is still `demo`.
 */
class GoogleSignUpController extends Controller
{
    use InvitationValidationRules, ProfileValidationRules;

    public function create(Request $request): Response|RedirectResponse
    {
        $identity = GoogleIdentity::in($request->session());

        if ($identity === null || $this->signedInForGood($request)) {
            return $this->sendOn($request);
        }

        return Inertia::render('auth/finish-signing-up', [
            'name' => $identity->name,
            'email' => $identity->email,
            'keeping' => $request->user()?->isDemo() ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $identity = GoogleIdentity::in($request->session());

        if ($identity === null || $this->signedInForGood($request)) {
            return $this->sendOn($request);
        }

        // Someone may have registered the email since the callback looked.
        $validated = Validator::make(
            ['email' => $identity->email, ...$request->only('name', 'invitation_code', 'tos_accepted')],
            [
                'name' => $this->nameRules(),
                'email' => [Rule::unique(User::class)],
                ...$this->invitationRules(),
            ],
            ['email.unique' => GoogleController::TAKEN, ...$this->invitationMessages()],
        )->validate();

        $demo = $request->user();

        $student = DB::transaction(function () use ($demo, $validated, $identity): User {
            // A demo row already carries a random password (12.1.2).
            $student = $demo ?? (new User)->forceFill(['password' => Str::random(64)]);
            $student->forceFill([
                'name' => $validated['name'],
                'email' => $identity->email,
                'has_chosen_password' => false,
                'tos_accepted_at' => now(),
                'plan' => 'standard',
            ]);
            $student->save();
            $student->googleAccount()->create(['sub' => $identity->sub]);

            return $student;
        });

        if ($demo !== null) {
            GoogleIdentity::forget($request->session());

            return redirect()->route('dashboard');
        }

        return GoogleSignIn::complete($request, $student);
    }

    /**
     * A full account has nothing to sign up for.
     */
    private function signedInForGood(Request $request): bool
    {
        return $request->user() !== null && ! $request->user()->isDemo();
    }

    private function sendOn(Request $request): RedirectResponse
    {
        return redirect()->route($request->user() === null ? 'login' : 'dashboard');
    }
}
