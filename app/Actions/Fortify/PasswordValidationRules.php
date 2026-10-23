<?php

namespace App\Actions\Fortify;

use App\Rules\ClaveCompatible;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', new ClaveCompatible, Password::default(), 'confirmed'];
    }
}
