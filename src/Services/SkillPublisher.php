<?php

namespace Whilesmart\Skills\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Whilesmart\Skills\Concerns\EnsuresSkillAccess;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Events\SkillVersionPublished;
use Whilesmart\Skills\Events\SkillVersionRevoked;
use Whilesmart\Skills\Exceptions\SkillValidationFailed;
use Whilesmart\Skills\Exceptions\SkillVersionUnavailable;
use Whilesmart\Skills\Exceptions\StaleDraftRevision;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Models\SkillVersionAvailability;
use Whilesmart\Skills\Support\Actor;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillPublisher
{
    use EnsuresSkillAccess;

    public function __construct(protected readonly PortableSkills $portable) {}

    public function publish(Skill $skill, int $expectedRevision, ?Authenticatable $actor = null): SkillVersion
    {
        $this->ensureSkillAccess($skill, $actor, SkillAuthorizer::PUBLISH);

        return DB::transaction(function () use ($skill, $expectedRevision, $actor) {
            $locked = SkillsConfig::query('skill')->whereKey($skill->getKey())->lockForUpdate()->first();

            if (! $locked instanceof Skill) {
                throw SkillVersionUnavailable::for($skill->uuid);
            }

            if ($locked->draft_revision !== $expectedRevision) {
                throw new StaleDraftRevision($expectedRevision, $locked->draft_revision);
            }

            $uuid = (string) Str::uuid();
            $number = $locked->latest_version_number + 1;
            $files = $locked->draft_files;
            $provenance = array_merge($locked->draft_provenance ?? [], [
                'catalogue' => [
                    'skill' => $locked->uuid,
                    'version' => $uuid,
                    'number' => $number,
                    'draft_revision' => $locked->draft_revision,
                ],
            ]);

            $portable = $this->portable->forFolder($files, $locked->name, $provenance);
            $problems = $this->portable->problems($portable);

            if ($this->portable->hasErrors($problems)) {
                throw new SkillValidationFailed($problems);
            }

            $versionClass = SkillsConfig::model('version');
            $version = new $versionClass;
            $version->forceFill([
                'uuid' => $uuid,
                'skill_id' => $locked->getKey(),
                'name' => $locked->name,
                'number' => $number,
                'files' => $files,
                'content_hash' => $portable->contentHash(),
                'frontmatter' => $portable->frontmatter,
                'body' => $portable->body,
                'provenance' => $provenance,
                ...Actor::columns($actor, 'created_by'),
            ])->save();

            $availabilityClass = SkillsConfig::model('availability');
            (new $availabilityClass)->forceFill([
                'skill_version_id' => $version->getKey(),
                'status' => SkillVersionAvailability::ACTIVE,
                ...Actor::columns($actor, 'changed_by'),
            ])->save();

            $locked->forceFill(['latest_version_number' => $number])->save();

            event(new SkillVersionPublished($version, $actor));

            return $version;
        });
    }

    public function revoke(SkillVersion $version, ?string $reason = null, ?Authenticatable $actor = null): SkillVersion
    {
        $skill = $version->skill()->withTrashed()->first();

        if (! $skill instanceof Skill) {
            throw SkillVersionUnavailable::for($version->uuid);
        }

        $this->ensureSkillAccess($skill, $actor, SkillAuthorizer::REVOKE);

        DB::transaction(function () use ($version, $reason, $actor) {
            $availability = SkillsConfig::query('availability')
                ->where('skill_version_id', $version->getKey())
                ->lockForUpdate()
                ->first();

            if ($availability instanceof SkillVersionAvailability && ! $availability->isActive()) {
                return;
            }

            if (! $availability instanceof SkillVersionAvailability) {
                $class = SkillsConfig::model('availability');
                $availability = new $class;
                $availability->skill_version_id = $version->getKey();
            }

            $availability->forceFill([
                'status' => SkillVersionAvailability::REVOKED,
                'reason' => $reason === null ? null : mb_substr($reason, 0, 500),
                'revoked_at' => now(),
                ...Actor::columns($actor, 'changed_by'),
            ])->save();

            event(new SkillVersionRevoked($version, $reason, $actor));
        });

        $version->unsetRelation('availability');

        return $version;
    }
}
