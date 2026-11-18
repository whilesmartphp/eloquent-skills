<?php

namespace Whilesmart\Skills\Sources;

use Illuminate\Contracts\Auth\Authenticatable;
use Whilesmart\Skillpack\Contracts\SkillSource;
use Whilesmart\Skillpack\Pack;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Services\SkillVersionResolver;

final class ManagedSkillSource implements SkillSource
{
    /**
     * @param  list<int|string>  $versionIds  Version UUIDs, or internal version keys as integers.
     */
    public function __construct(
        private readonly string $ownerType,
        private readonly int|string $ownerId,
        private readonly string $scopeKey,
        private readonly array $versionIds,
        private readonly ?Authenticatable $actor = null,
    ) {}

    public function load(): Pack
    {
        $versions = app(SkillVersionResolver::class)->resolveMany(
            array_values(array_unique($this->versionIds, SORT_REGULAR)),
            $this->ownerType,
            $this->ownerId,
            $this->scopeKey,
            $this->actor,
        );

        return new Pack(array_map(fn (SkillVersion $version) => $version->toPortableSkill(), $versions));
    }
}
