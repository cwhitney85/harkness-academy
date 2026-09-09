<?php

namespace App\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * The app's default password rule, stated as lines a student can read.
 *
 * The rule itself differs by environment (`AppServiceProvider`), so the lines
 * are derived from it rather than written out a second time.
 */
final class PasswordRequirements
{
    /**
     * Pre:  `Password::defaults()` returns the rule the validator will apply.
     * Post: one line per condition that rule enforces, in the order the
     *       validator checks them. Never empty -- a minimum length always
     *       applies.
     *
     * @return list<string>
     */
    public static function inWords(): array
    {
        $applied = Password::defaults()->appliedRules();

        return array_values(array_filter([
            "At least {$applied['min']} characters",
            $applied['max'] ? "No more than {$applied['max']} characters" : null,
            match (true) {
                $applied['mixedCase'] => 'An uppercase letter and a lowercase letter',
                $applied['letters'] => 'At least one letter',
                default => null,
            },
            $applied['numbers'] ? 'At least one number' : null,
            $applied['symbols'] ? 'At least one symbol' : null,
            $applied['uncompromised'] ? 'Not a password that has appeared in a known data breach' : null,
        ]));
    }
}
