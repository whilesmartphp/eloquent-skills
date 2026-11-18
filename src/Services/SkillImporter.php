<?php

namespace Whilesmart\Skills\Services;

use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Whilesmart\Skillpack\Loader;
use Whilesmart\Skillpack\Skill as PortableSkill;
use Whilesmart\Skills\Concerns\EnsuresSkillAccess;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Events\SkillsImported;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Exceptions\SkillConflict;
use Whilesmart\Skills\Exceptions\SkillsException;
use Whilesmart\Skills\Imports\ImportContext;
use Whilesmart\Skills\Imports\SkillSourceRegistry;
use Whilesmart\Skills\Models\SkillImport;
use Whilesmart\Skills\Support\Actor;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillImporter
{
    use EnsuresSkillAccess;

    public function __construct(
        protected readonly SkillSourceRegistry $sources,
        protected readonly Loader $loader,
        protected readonly SkillDrafts $drafts,
        protected readonly PortableSkills $portable,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function import(
        string $ownerType,
        int|string $ownerId,
        ?string $scopeKey,
        string $source,
        array $input,
        bool $updateExisting = false,
        ?Authenticatable $actor = null,
    ): SkillImport {
        $scopeKey = SkillsConfig::scopeKey($scopeKey);
        $this->ensureOwnerAccess($actor, SkillAuthorizer::IMPORT, $ownerType, $ownerId, $scopeKey);

        $context = new ImportContext($ownerType, $ownerId, $scopeKey, $actor);
        $prepared = $this->sources->provider($source)->prepare($input, $context);

        $class = SkillsConfig::model('import');
        $import = new $class;
        $import->forceFill([
            'owner_type' => $ownerType,
            'owner_id' => (string) $ownerId,
            'scope_key' => $scopeKey,
            'source' => $source,
            'reference' => mb_substr($prepared->reference, 0, 2048),
            'status' => SkillImport::PENDING,
            ...Actor::columns($actor, 'actor'),
        ])->save();

        try {
            $skills = array_values($this->loader->load($prepared->source, $prepared->options)->all());
            $limit = SkillsConfig::limit('max_skills_per_import');

            if ($skills === []) {
                throw InvalidSkillBundle::unreadable('the source holds no skills.');
            }

            if (count($skills) > $limit) {
                throw InvalidSkillBundle::unreadable("the source holds more than $limit skills.");
            }

            $result = DB::transaction(fn () => array_map(
                fn (PortableSkill $skill) => $this->store($skill, $import, $updateExisting, $prepared->recordSourceProvenance, $actor),
                $skills,
            ));
        } catch (SkillConflict|InvalidSkillBundle $exception) {
            return $this->fail($import, $prepared->redact($exception->getMessage()), $actor);
        } catch (SkillsException $exception) {
            $this->fail($import, $prepared->redact($exception->getMessage()), $actor);

            throw $exception;
        } catch (Exception $exception) {
            return $this->fail($import, 'The source could not be loaded: '.$prepared->redact($exception->getMessage()), $actor);
        }

        $import->forceFill([
            'status' => SkillImport::COMPLETED,
            'result' => ['skills' => $result],
            'completed_at' => now(),
        ])->save();

        event(new SkillsImported($import, $actor));

        return $import;
    }

    /**
     * @return array<string, mixed>
     */
    protected function store(PortableSkill $portable, SkillImport $import, bool $updateExisting, bool $recordSourceProvenance, ?Authenticatable $actor): array
    {
        $files = [];

        foreach ($portable->files as $path => $bytes) {
            $files[(string) $path] = $bytes;
        }

        $provenance = [
            'import' => [
                'id' => $import->uuid,
                'source' => $import->source,
                'reference' => $import->reference,
            ],
        ];

        if ($recordSourceProvenance && $portable->provenance !== []) {
            $provenance['source'] = $portable->provenance;
        }

        [$files, $name] = $this->drafts->read($files);
        $existing = $this->drafts->findLive($import->owner_type, $import->owner_id, $import->scope_key, $name);

        if ($existing !== null && ! $updateExisting) {
            throw SkillConflict::name($name);
        }

        $skill = $existing === null
            ? $this->drafts->create($import->owner_type, $import->owner_id, $import->scope_key, $files, null, $actor, $provenance)
            : $this->drafts->update($existing, $existing->draft_revision, $files, null, $actor, $provenance);

        return [
            'name' => $name,
            'skill' => $skill->uuid,
            'action' => $existing === null ? 'created' : 'updated',
            'draft_revision' => $skill->draft_revision,
            'problems' => $this->portable->problems($this->portable->forFolder($files, $name)),
        ];
    }

    protected function fail(SkillImport $import, string $message, ?Authenticatable $actor): SkillImport
    {
        $import->forceFill([
            'status' => SkillImport::FAILED,
            'error' => mb_substr($message, 0, 2000),
            'completed_at' => now(),
        ])->save();

        event(new SkillsImported($import, $actor));

        return $import;
    }
}
