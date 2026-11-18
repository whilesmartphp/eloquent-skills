<?php

namespace Whilesmart\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Whilesmart\Skills\Http\Requests\Concerns\ReadsSkillFiles;
use Whilesmart\Skills\Http\Rules\BoundedJson;

class UpdateSkillRequest extends FormRequest
{
    use ReadsSkillFiles;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'metadata' => ['sometimes', 'nullable', 'array', new BoundedJson],
            ...$this->fileRules(false),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkSkillFiles($validator));
    }
}
