<?php

namespace Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

class GrantingOwnerAuthorizer implements OwnerAuthorizer
{
    /**
     * @var array<string, list<array{0: string, 1: string}>>
     */
    private array $grants = [];

    public function grant(Authenticatable $actor, Model $owner): void
    {
        $this->grants[$this->key($actor)][] = [$owner->getMorphClass(), (string) $owner->getKey()];
    }

    public function authorize(?Authenticatable $user, string $ownerType, mixed $ownerId): bool
    {
        if ($user === null) {
            return false;
        }

        foreach ($this->grants[$this->key($user)] ?? [] as [$type, $id]) {
            if ($type === $ownerType && $id === (string) $ownerId) {
                return true;
            }
        }

        return false;
    }

    public function scope(Builder $query, ?Authenticatable $user, string $ownerTypeColumn = 'owner_type', string $ownerIdColumn = 'owner_id'): Builder
    {
        $grants = $user === null ? [] : ($this->grants[$this->key($user)] ?? []);

        if ($grants === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($grants, $ownerTypeColumn, $ownerIdColumn) {
            foreach ($grants as [$type, $id]) {
                $inner->orWhere(fn (Builder $grant) => $grant->where($ownerTypeColumn, $type)->where($ownerIdColumn, $id));
            }
        });
    }

    private function key(Authenticatable $actor): string
    {
        return $actor::class.'#'.$actor->getAuthIdentifier();
    }
}
