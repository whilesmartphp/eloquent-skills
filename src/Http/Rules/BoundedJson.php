<?php

namespace Whilesmart\Skills\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BoundedJson implements ValidationRule
{
    public function __construct(private readonly int $maxBytes = 16384, private readonly int $maxDepth = 8) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, $this->maxDepth);

        if ($encoded === false || strlen($encoded) > $this->maxBytes) {
            $fail("The :attribute must encode to at most {$this->maxBytes} bytes of JSON.");
        }
    }
}
