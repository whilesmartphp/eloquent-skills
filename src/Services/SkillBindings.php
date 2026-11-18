<?php

namespace Whilesmart\Skills\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Whilesmart\Skills\Concerns\EnsuresSkillAccess;
use Whilesmart\Skills\Contracts\BindingAuthorizer;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Events\SkillUnbound;
use Whilesmart\Skills\Events\SkillVersionAssigned;
use Whilesmart\Skills\Exceptions\SkillAccessDenied;
use Whilesmart\Skills\Exceptions\SkillBindingRejected;
use Whilesmart\Skills\Exceptions\SkillVersionUnavailable;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillBinding;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillBindings
{
    use EnsuresSkillAccess;

    public function __construct(protected readonly BindingAuthorizer $authorizer) {}

    public function assign(Model $skillable, SkillVersion $version, ?Authenticatable $actor = null): SkillBinding
    {
        $this->ensureSkillable($skillable);

        return DB::transaction(function () use ($skillable, $version, $actor) {
            $current = SkillsConfig::query('version')
                ->whereKey($version->getKey())
                ->available()
                ->with(['skill', 'availability'])
                ->lockForUpdate()
                ->first();

            if (! $current instanceof SkillVersion || ! $current->skill instanceof Skill) {
                throw SkillVersionUnavailable::for((string) $version->uuid);
            }

            $skill = $current->skill;

            if (! $skill->belongsToOwner($skillable->skillOwnerType(), $skillable->skillOwnerId(), $skillable->skillScopeKey())) {
                throw SkillBindingRejected::ownerMismatch();
            }

            if (! $this->authorizer->authorize($skillable, $current, $actor)) {
                throw new SkillAccessDenied;
            }

            $binding = SkillsConfig::query('binding')->firstOrNew([
                'skillable_type' => $skillable->getMorphClass(),
                'skillable_id' => (string) $skillable->getKey(),
                'skill_id' => $skill->getKey(),
            ]);

            $previous = $binding->exists ? (int) $binding->skill_version_id : null;
            $binding->skill_version_id = $current->getKey();
            $binding->save();

            if ($previous !== (int) $current->getKey()) {
                event(new SkillVersionAssigned($binding, $skillable, $current, $actor));
            }

            return $binding;
        });
    }

    public function detach(Model $skillable, Skill $skill, ?Authenticatable $actor = null): bool
    {
        $this->ensureSkillable($skillable);
        $this->ensureOwnerAccess($actor, SkillAuthorizer::BIND, $skillable->skillOwnerType(), $skillable->skillOwnerId(), $skillable->skillScopeKey());

        return DB::transaction(function () use ($skillable, $skill, $actor) {
            $binding = SkillsConfig::query('binding')
                ->where('skillable_type', $skillable->getMorphClass())
                ->where('skillable_id', (string) $skillable->getKey())
                ->where('skill_id', $skill->getKey())
                ->lockForUpdate()
                ->first();

            if (! $binding instanceof SkillBinding) {
                return false;
            }

            $binding->delete();
            event(new SkillUnbound($binding, $skillable, $actor));

            return true;
        });
    }

    protected function ensureSkillable(Model $skillable): void
    {
        if (! method_exists($skillable, 'skillOwnerType') || ! method_exists($skillable, 'skillBindings')) {
            throw SkillBindingRejected::notSkillable();
        }

        if (! $skillable->exists) {
            throw SkillBindingRejected::unsaved();
        }
    }
}
