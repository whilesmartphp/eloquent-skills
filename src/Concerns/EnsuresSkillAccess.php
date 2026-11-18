<?php

namespace Whilesmart\Skills\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Exceptions\SkillAccessDenied;
use Whilesmart\Skills\Models\Skill;

trait EnsuresSkillAccess
{
    protected function ensureOwnerAccess(?Authenticatable $actor, string $ability, string $ownerType, int|string $ownerId, string $scopeKey): void
    {
        if (! app(SkillAuthorizer::class)->authorize($actor, $ability, $ownerType, $ownerId, $scopeKey)) {
            throw new SkillAccessDenied;
        }
    }

    protected function ensureSkillAccess(Skill $skill, ?Authenticatable $actor, string $ability): void
    {
        $this->ensureOwnerAccess($actor, $ability, $skill->owner_type, $skill->owner_id, $skill->scope_key);
    }
}
