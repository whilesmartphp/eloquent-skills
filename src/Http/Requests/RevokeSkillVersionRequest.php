<?php

namespace Whilesmart\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevokeSkillVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
