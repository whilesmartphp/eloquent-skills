<?php

namespace Whilesmart\Skills\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

interface SkillAuthorizer
{
    public const VIEW = 'view';

    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const DELETE = 'delete';

    public const PUBLISH = 'publish';

    public const REVOKE = 'revoke';

    public const IMPORT = 'import';

    public const BIND = 'bind';

    public const READ = 'read';

    public function authorize(?Authenticatable $actor, string $ability, string $ownerType, int|string $ownerId, string $scopeKey): bool;

    public function scope(Builder $query, ?Authenticatable $actor, string $ability = self::VIEW): Builder;
}
