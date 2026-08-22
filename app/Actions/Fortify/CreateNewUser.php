<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * Registration requires an 18-or-older attestation bundled into Terms of
     * Service acceptance (spec 1.2). OpenTutor does not serve under-18
     * accounts, so an unchecked attestation fails validation and no user row
     * is created.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'tos_accepted' => ['accepted'],
        ], [
            'tos_accepted.accepted' => 'You must be 18 or older and accept the Terms of Service to create an account.',
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'tos_accepted_at' => now(),
        ]);
    }
}
