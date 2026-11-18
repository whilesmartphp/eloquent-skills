<?php

namespace Whilesmart\Skills\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Whilesmart\Skills\Concerns\EnsuresSkillAccess;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Exceptions\SkillVersionUnavailable;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillVersionResolver
{
    use EnsuresSkillAccess;

    public function resolve(string $uuid, string $ownerType, int|string $ownerId, string $scopeKey, ?Authenticatable $actor = null): SkillVersion
    {
        return $this->resolveMany([$uuid], $ownerType, $ownerId, $scopeKey, $actor)[0];
    }

    /**
     * Strings are version UUIDs; integers are internal version keys.
     *
     * @param  list<int|string>  $ids
     * @return list<SkillVersion>
     */
    public function resolveMany(array $ids, string $ownerType, int|string $ownerId, string $scopeKey, ?Authenticatable $actor = null): array
    {
        $scopeKey = SkillsConfig::scopeKey($scopeKey);
        $this->ensureOwnerAccess($actor, SkillAuthorizer::READ, $ownerType, $ownerId, $scopeKey);

        $resolved = [];

        foreach (array_values($ids) as $id) {
            $resolved[] = $this->find($id, $ownerType, $ownerId, $scopeKey);
        }

        return $resolved;
    }

    protected function find(mixed $id, string $ownerType, int|string $ownerId, string $scopeKey): SkillVersion
    {
        if (is_int($id)) {
            $column = 'id';
        } elseif (is_string($id) && Str::isUuid($id)) {
            $column = 'uuid';
        } else {
            throw SkillVersionUnavailable::for(is_scalar($id) ? (string) $id : '');
        }

        $version = SkillsConfig::query('version')
            ->where($column, $id)
            ->available()
            ->ownedBy($ownerType, $ownerId, $scopeKey)
            ->with(['skill', 'availability'])
            ->first();

        if (! $version instanceof SkillVersion) {
            throw SkillVersionUnavailable::for((string) $id);
        }

        return $version;
    }
}
