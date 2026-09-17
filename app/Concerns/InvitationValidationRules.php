<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * What an account must bring to become a full one: the 18-or-older attestation
 * (spec 1.2) and the invitation code (spec 1.6, ledger D45).
 *
 * Shared by registration and demo conversion (12.4.3), so the door has one
 * lock whichever way a student comes through it.
 */
trait InvitationValidationRules
{
    /**
     * An unset code matches nothing, so the door is shut rather than open when
     * the environment is missing it.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function invitationRules(): array
    {
        return [
            'tos_accepted' => ['accepted'],
            'invitation_code' => ['required', 'string', Rule::in([config('auth.invitation_code')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function invitationMessages(): array
    {
        return [
            'tos_accepted.accepted' => 'You must be 18 or older and accept the Terms of Service to create an account.',
            'invitation_code.required' => 'Registration is by invitation. Enter the code you were sent.',
            'invitation_code.in' => 'That is not the invitation code. Check the one you were sent.',
        ];
    }
}
