<?php

namespace Whilesmart\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
