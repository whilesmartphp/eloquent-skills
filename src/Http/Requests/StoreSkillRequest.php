<?php

namespace Whilesmart\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Http\Requests\Concerns\AuthorizesPostedOwner;
use Whilesmart\Skills\Http\Requests\Concerns\ReadsSkillFiles;
use Whilesmart\Skills\Http\Rules\BoundedJson;
use Whilesmart\Skills\Http\Rules\OwnerIdentifier;
use Whilesmart\Skills\Support\SkillsConfig;

class StoreSkillRequest extends FormRequest
{
    use AuthorizesPostedOwner;
    use ReadsSkillFiles;

    public function authorize(): bool
    {
        return $this->authorizePostedOwner(SkillAuthorizer::CREATE);
    }

    public function rules(): array
    {
        return [
            'owner_type' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', new OwnerIdentifier],
            'scope_key' => ['nullable', 'string', 'regex:'.SkillsConfig::SCOPE_PATTERN],
            'metadata' => ['nullable', 'array', new BoundedJson],
            ...$this->fileRules(true),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->checkSkillFiles($validator));
    }
}
