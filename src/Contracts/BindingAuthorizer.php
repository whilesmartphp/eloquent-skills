<?php

namespace Whilesmart\Skills\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Skills\Models\SkillVersion;

interface BindingAuthorizer
{
    public function authorize(Model $skillable, SkillVersion $version, ?Authenticatable $actor): bool;
}
