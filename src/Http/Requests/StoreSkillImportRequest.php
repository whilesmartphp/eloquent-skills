<?php

namespace Whilesmart\Skills\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Http\Requests\Concerns\AuthorizesPostedOwner;
use Whilesmart\Skills\Http\Rules\OwnerIdentifier;
use Whilesmart\Skills\Imports\SkillSourceRegistry;
use Whilesmart\Skills\Support\SkillsConfig;

class StoreSkillImportRequest extends FormRequest
{
    use AuthorizesPostedOwner;

    public function authorize(): bool
    {
        return $this->authorizePostedOwner(SkillAuthorizer::IMPORT);
    }

    public function rules(): array
    {
        $rules = [
            'owner_type' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', new OwnerIdentifier],
            'scope_key' => ['nullable', 'string', 'regex:'.SkillsConfig::SCOPE_PATTERN],
            'source' => ['required', 'string', 'max:64'],
            'input' => ['nullable', 'array'],
            'update_existing' => ['sometimes', 'boolean'],
        ];

        $source = $this->input('source');

        if (! is_string($source) || $source === '') {
            return $rules;
        }

        $provider = app(SkillSourceRegistry::class)->provider($source);
        $providerRules = $provider->rules();
        $rules['input'] = $providerRules === []
            ? ['nullable', 'array', 'max:0']
            : ['nullable', 'array:'.implode(',', array_keys($providerRules))];

        foreach ($providerRules as $key => $rule) {
            $rules['input.'.$key] = $rule;
        }

        return $rules;
    }
}
