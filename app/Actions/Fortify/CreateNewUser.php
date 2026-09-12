<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Registration requires an 18-or-older attestation bundled into Terms of
     * Service acceptance (spec 1.2). Harkness Academy does not serve under-18
     * accounts, so an unchecked attestation fails validation and no user row
     * is created.
     *
     * It also requires the invitation code (spec 1.6, ledger D45) -- one
     * shared value in config, checked here beside the attestation. An unset
     * code matches nothing, so the door is shut rather than open when the
     * environment is missing it.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'tos_accepted' => ['accepted'],
            'invitation_code' => ['required', 'string', Rule::in([config('auth.invitation_code')])],
        ], [
            'tos_accepted.accepted' => 'You must be 18 or older and accept the Terms of Service to create an account.',
            'invitation_code.required' => 'Registration is by invitation. Enter the code you were sent.',
            'invitation_code.in' => 'That is not the invitation code. Check the one you were sent.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'tos_accepted_at' => now(),
        ]);
    }
}
