<?php

namespace Whilesmart\Skills\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OwnerIdentifier implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_int($value) && $value > 0) {
            return;
        }

        if (is_string($value) && $value !== '' && strlen($value) <= 64 && preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
            return;
        }

        $fail('The :attribute must be an integer or an identifier of at most 64 characters.');
    }
}
