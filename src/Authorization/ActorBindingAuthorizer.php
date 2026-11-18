<?php

namespace Whilesmart\Skills\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Skills\Contracts\BindingAuthorizer;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Models\SkillVersion;

class ActorBindingAuthorizer implements BindingAuthorizer
{
    public function __construct(private readonly SkillAuthorizer $skills) {}

    public function authorize(Model $skillable, SkillVersion $version, ?Authenticatable $actor): bool
    {
        $skill = $version->skill;

        if ($skill === null) {
            return false;
        }

        return $this->skills->authorize($actor, SkillAuthorizer::BIND, $skill->owner_type, $skill->owner_id, $skill->scope_key);
    }
}
