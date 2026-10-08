<?php

namespace App\Http\Controllers\Settings;

use App\Auth\PasswordRequirements;
use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    use PasswordValidationRules;

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            /* @chisel-2fa */
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            /* @end-chisel-2fa */
            /* @chisel-passkeys */
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            /* @end-chisel-passkeys */
            'hasChosenPassword' => $request->user()->has_chosen_password,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'passwordRequirements' => PasswordRequirements::inWords(),
        ];

        /* @chisel-2fa */
        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
        /* @end-chisel-2fa */

        return Inertia::render('settings/security', $props);
    }

    /**
     * Set a password for an account whose own was never shown to anyone -- a
     * Google sign-up (spec 18.4.3, ledger D74). There is no current password
     * to ask for, so the route sits behind password confirmation instead,
     * which a Google-only student passes with Google (18.4.2).
     *
     * Pre: the account has not chosen a password; one that has changes it
     * with `update`, which asks for the current one.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->has_chosen_password, 403);

        $validated = $request->validate(['password' => $this->passwordRules()]);

        $request->user()->forceFill(['password' => $validated['password'], 'has_chosen_password' => true])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password set.')]);

        return back();
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
