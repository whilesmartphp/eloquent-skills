<?php

namespace Whilesmart\Skills\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\Skills\Models\SkillImport;

class SkillsImported implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly SkillImport $import, public readonly ?Authenticatable $actor = null) {}
}
