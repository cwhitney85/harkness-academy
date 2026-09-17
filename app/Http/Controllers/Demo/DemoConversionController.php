<?php

namespace App\Http\Controllers\Demo;

use App\Auth\PasswordRequirements;
use App\Concerns\InvitationValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S14 -- keeping the demo (spec 12.4).
 *
 * Conversion is registration onto the row that already exists. Name, email and
 * password are attached, the plan becomes `standard`, and nothing is copied,
 * because nothing moves: the working knowledge the demo wrote is already this
 * student's (12.4.2). The door is the one registration has -- the attestation
 * and the invitation code (12.4.3, ledger D45).
 *
 * The course carries on. A demo is lesson 1 of an ordinary course (12.2), so
 * signing up changes nothing about it -- the lessons after the first simply
 * open (12.4.4).
 *
 * Pre:  the student is signed in on the `demo` plan. Anyone else has no demo
 *       to keep and is sent to the dashboard.
 * Post: on success, a `standard` account on its dashboard, every course as it
 *       was. On a validation failure, the row is unchanged.
 */
class DemoConversionController extends Controller
{
    use InvitationValidationRules, PasswordValidationRules, ProfileValidationRules;

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->isDemo()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/keep-course', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordRequirements' => PasswordRequirements::inWords(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();

        if (! $student->isDemo()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            ...$this->profileRules($student->id),
            'password' => $this->passwordRules(),
            ...$this->invitationRules(),
        ], $this->invitationMessages());

        $student->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'tos_accepted_at' => now(),
            'plan' => 'standard',
        ])->save();

        return redirect()->route('dashboard');
    }
}
