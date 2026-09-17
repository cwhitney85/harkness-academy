<?php

namespace App\Actions\Fortify;

use App\Concerns\InvitationValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use InvitationValidationRules, PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Registration requires an 18-or-older attestation bundled into Terms of
     * Service acceptance (spec 1.2). Harkness Academy does not serve under-18
     * accounts, so an unchecked attestation fails validation and no user row
     * is created.
     *
     * It also requires the invitation code (spec 1.6, ledger D45). Both live in
     * InvitationValidationRules, which demo conversion shares (spec 12.4.3).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            ...$this->invitationRules(),
        ], $this->invitationMessages())->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'tos_accepted_at' => now(),
        ]);
    }
}
