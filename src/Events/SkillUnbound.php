<?php

namespace Whilesmart\Skills\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\Skills\Models\SkillBinding;

class SkillUnbound implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly SkillBinding $binding,
        public readonly Model $skillable,
        public readonly ?Authenticatable $actor = null,
    ) {}
}
