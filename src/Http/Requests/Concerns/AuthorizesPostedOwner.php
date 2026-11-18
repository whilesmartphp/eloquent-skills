<?php

namespace Whilesmart\Skills\Http\Requests\Concerns;

use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Support\SkillsConfig;

trait AuthorizesPostedOwner
{
    protected function authorizePostedOwner(string $ability): bool
    {
        $ownerType = $this->input('owner_type');
        $ownerId = $this->input('owner_id');
        $scopeKey = $this->input('scope_key');

        if (! is_string($ownerType) || $ownerType === '' || (! is_int($ownerId) && ! is_string($ownerId)) || $ownerId === '') {
            return true;
        }

        if ($scopeKey !== null && (! is_string($scopeKey) || ! preg_match(SkillsConfig::SCOPE_PATTERN, $scopeKey))) {
            return true;
        }

        return app(SkillAuthorizer::class)->authorize(
            $this->user(),
            $ability,
            $ownerType,
            $ownerId,
            SkillsConfig::scopeKey($scopeKey),
        );
    }
}
