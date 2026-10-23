<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ClaveCompatible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 72 || str_contains($value, "\0")) {
            $fail('La contraseña admite como máximo 72 bytes y no admite caracteres nulos.');
        }
    }
}
