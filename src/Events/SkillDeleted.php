<?php

namespace Whilesmart\Skills\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\Skills\Models\Skill;

class SkillDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Skill $skill, public readonly ?Authenticatable $actor = null) {}
}
