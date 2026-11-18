<?php

namespace Whilesmart\Skills\Authorization;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Whilesmart\OwnerAccess\Authorizers\AllowAllAuthorizer;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;
use Whilesmart\Skills\Contracts\SkillAuthorizer;

class OwnerAccessSkillAuthorizer implements SkillAuthorizer
{
    public function __construct(private readonly Container $container) {}

    public function authorize(?Authenticatable $actor, string $ability, string $ownerType, int|string $ownerId, string $scopeKey): bool
    {
        if ($scopeKey !== (string) config('skills.default_scope_key', 'default')) {
            return false;
        }

        $owners = $this->owners();

        return $owners !== null && $owners->authorize($actor, $ownerType, $ownerId) === true;
    }

    public function scope(Builder $query, ?Authenticatable $actor, string $ability = self::VIEW): Builder
    {
        $owners = $this->owners();

        if ($owners === null) {
            return $query->whereRaw('1 = 0');
        }

        $table = $query->getModel()->getTable();

        return $owners->scope($query, $actor, $table.'.owner_type', $table.'.owner_id')
            ->where($table.'.scope_key', (string) config('skills.default_scope_key', 'default'));
    }

    protected function owners(): ?OwnerAuthorizer
    {
        if (! $this->container->bound(OwnerAuthorizer::class)) {
            return null;
        }

        $owners = $this->container->make(OwnerAuthorizer::class);

        return $owners instanceof AllowAllAuthorizer ? null : $owners;
    }
}
