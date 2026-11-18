<?php

namespace Whilesmart\Skills\Traits;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillBinding;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Services\SkillBindings;
use Whilesmart\Skills\Support\SkillsConfig;

trait HasSkills
{
    public function skillBindings(): MorphMany
    {
        return $this->morphMany(SkillsConfig::model('binding'), 'skillable');
    }

    public function skills(): BelongsToMany
    {
        return $this->morphToMany(
            SkillsConfig::model('skill'),
            'skillable',
            SkillsConfig::table('bindings'),
            'skillable_id',
            'skill_id',
        )->withPivot(['skill_version_id', 'metadata'])->withTimestamps();
    }

    public function assignSkillVersion(SkillVersion $version, ?Authenticatable $actor = null): SkillBinding
    {
        return app(SkillBindings::class)->assign($this, $version, $actor);
    }

    public function detachSkill(Skill $skill, ?Authenticatable $actor = null): bool
    {
        return app(SkillBindings::class)->detach($this, $skill, $actor);
    }

    public function skillOwnerType(): string
    {
        $type = $this->getAttributes()['owner_type'] ?? null;

        return is_string($type) && $type !== '' ? $type : $this->getMorphClass();
    }

    public function skillOwnerId(): int|string
    {
        $attributes = $this->getAttributes();
        $type = $attributes['owner_type'] ?? null;

        if (is_string($type) && $type !== '' && (is_int($attributes['owner_id'] ?? null) || is_string($attributes['owner_id'] ?? null))) {
            return $attributes['owner_id'];
        }

        return $this->getKey();
    }

    public function skillScopeKey(): string
    {
        $scope = $this->getAttributes()['scope_key'] ?? null;

        return is_string($scope) && $scope !== '' ? $scope : SkillsConfig::defaultScopeKey();
    }

    /**
     * @return array<string, string>
     */
    public function pinnedSkillVersionIds(): array
    {
        return $this->skillBindings()
            ->with(['skill', 'version'])
            ->get()
            ->filter(fn (SkillBinding $binding) => $binding->skill !== null && $binding->version !== null)
            ->mapWithKeys(fn (SkillBinding $binding) => [$binding->skill->public_id => $binding->version->public_id])
            ->all();
    }
}
