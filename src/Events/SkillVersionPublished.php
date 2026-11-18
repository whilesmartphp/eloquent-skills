<?php

namespace Whilesmart\Skills\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\Skills\Models\SkillVersion;

class SkillVersionPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly SkillVersion $version, public readonly ?Authenticatable $actor = null) {}
}
