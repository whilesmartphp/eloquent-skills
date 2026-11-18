<?php

namespace Whilesmart\Skills\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Whilesmart\Skills\Concerns\EnsuresSkillAccess;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Events\SkillCreated;
use Whilesmart\Skills\Events\SkillDeleted;
use Whilesmart\Skills\Events\SkillDraftUpdated;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Exceptions\SkillConflict;
use Whilesmart\Skills\Exceptions\StaleDraftRevision;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Support\FileBundle;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillDrafts
{
    use EnsuresSkillAccess;

    public function __construct(protected readonly PortableSkills $portable) {}

    /**
     * @param  array<string, string>  $files
     */
    public function create(
        string $ownerType,
        int|string $ownerId,
        ?string $scopeKey,
        array $files,
        ?array $metadata = null,
        ?Authenticatable $actor = null,
        array $provenance = [],
    ): Skill {
        $scopeKey = SkillsConfig::scopeKey($scopeKey);
        $this->ensureOwnerAccess($actor, SkillAuthorizer::CREATE, $ownerType, $ownerId, $scopeKey);

        [$files, $name, $description] = $this->read($files);

        return DB::transaction(function () use ($ownerType, $ownerId, $scopeKey, $files, $name, $description, $metadata, $provenance, $actor) {
            $this->ensureNameFree($ownerType, $ownerId, $scopeKey, $name);

            $class = SkillsConfig::model('skill');
            $skill = new $class;
            $skill->forceFill([
                'owner_type' => $ownerType,
                'owner_id' => (string) $ownerId,
                'scope_key' => $scopeKey,
                'name' => $name,
                'active_name' => $name,
                'description' => $description,
                'draft_revision' => 1,
                'draft_files' => $files,
                'draft_provenance' => $provenance === [] ? null : $provenance,
                'metadata' => $metadata,
            ]);

            $this->save($skill, $name);

            event(new SkillCreated($skill, $actor));

            return $skill;
        });
    }

    /**
     * @param  array<string, string>|null  $files
     */
    public function update(
        Skill $skill,
        int $expectedRevision,
        ?array $files = null,
        ?array $metadata = null,
        ?Authenticatable $actor = null,
        ?array $provenance = null,
    ): Skill {
        $this->ensureSkillAccess($skill, $actor, SkillAuthorizer::UPDATE);

        $read = $files === null ? null : $this->read($files);

        return DB::transaction(function () use ($skill, $expectedRevision, $read, $metadata, $actor, $provenance) {
            $locked = $this->lock($skill);

            if ($locked->draft_revision !== $expectedRevision) {
                throw new StaleDraftRevision($expectedRevision, $locked->draft_revision);
            }

            if ($read !== null) {
                [$files, $name, $description] = $read;

                if ($name !== $locked->name) {
                    if ($locked->latest_version_number > 0) {
                        throw InvalidSkillBundle::renameAfterPublication();
                    }

                    $this->ensureNameFree($locked->owner_type, $locked->owner_id, $locked->scope_key, $name, $locked->getKey());
                    $locked->name = $name;
                    $locked->active_name = $name;
                }

                $locked->description = $description;
                $locked->draft_files = $files;

                if ($provenance !== null) {
                    $locked->draft_provenance = $provenance === [] ? null : $provenance;
                }
            }

            if ($metadata !== null) {
                $locked->metadata = $metadata;
            }

            $locked->draft_revision = $locked->draft_revision + 1;
            $this->save($locked, $locked->name);

            event(new SkillDraftUpdated($locked, $actor));

            return $locked;
        });
    }

    public function delete(Skill $skill, ?Authenticatable $actor = null): void
    {
        $this->ensureSkillAccess($skill, $actor, SkillAuthorizer::DELETE);

        DB::transaction(function () use ($skill, $actor) {
            $locked = $this->lock($skill);
            $locked->active_name = null;
            $locked->save();
            $locked->delete();

            event(new SkillDeleted($locked, $actor));
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function problems(Skill $skill): array
    {
        return $this->portable->problems($this->portable->forFolder($skill->draft_files, $skill->name, $skill->draft_provenance ?? []));
    }

    /**
     * @param  array<string, string>  $files
     * @return array{name: string, problems: list<array<string, mixed>>}
     */
    public function check(array $files): array
    {
        [$files, $name] = $this->read($files);

        return [
            'name' => $name,
            'problems' => $this->portable->problems($this->portable->forFolder($files, $name)),
        ];
    }

    /**
     * @param  array<string, string>  $files
     * @return array{0: array<string, string>, 1: string, 2: string|null}
     */
    public function read(array $files): array
    {
        $files = FileBundle::check($files);
        $portable = $this->portable->inspect($files);
        $name = $portable->name();

        if (! SkillsConfig::validName($name)) {
            throw InvalidSkillBundle::invalidName();
        }

        $description = trim($portable->description());

        return [$files, $name, $description === '' ? null : mb_substr($description, 0, 1024)];
    }

    public function findLive(string $ownerType, int|string $ownerId, string $scopeKey, string $name): ?Skill
    {
        $skill = SkillsConfig::query('skill')
            ->where('owner_type', $ownerType)
            ->where('owner_id', (string) $ownerId)
            ->where('scope_key', $scopeKey)
            ->where('active_name', $name)
            ->first();

        return $skill instanceof Skill ? $skill : null;
    }

    protected function ensureNameFree(string $ownerType, int|string $ownerId, string $scopeKey, string $name, int|string|null $except = null): void
    {
        $existing = $this->findLive($ownerType, $ownerId, $scopeKey, $name);

        if ($existing !== null && $existing->getKey() !== $except) {
            throw SkillConflict::name($name);
        }
    }

    protected function save(Skill $skill, string $name): void
    {
        try {
            $skill->save();
        } catch (UniqueConstraintViolationException) {
            throw SkillConflict::name($name);
        }
    }

    protected function lock(Skill $skill): Skill
    {
        $locked = SkillsConfig::query('skill')->whereKey($skill->getKey())->lockForUpdate()->firstOrFail();

        return $locked instanceof Skill ? $locked : throw new LogicException('The configured skill model is invalid.');
    }
}
