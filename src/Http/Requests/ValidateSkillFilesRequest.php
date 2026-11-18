<?php

namespace Whilesmart\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Whilesmart\Skills\Http\Requests\Concerns\ReadsSkillFiles;

class ValidateSkillFilesRequest extends FormRequest
{
    use ReadsSkillFiles;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return $this->fileRules(true);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkSkillFiles($validator));
    }
}
