<?php

namespace App\Http\Requests\Settings;

use App\Admin\ViewingAs;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()->id);

        // Password resets go to this address, so an admin acting as the
        // student who changed it could take the account over (13.4.8).
        if (ViewingAs::viewing()) {
            $rules['email'][] = Rule::in([$this->user()->email]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.in' => 'A student\'s email stays theirs, even while you view as them.'];
    }
}
