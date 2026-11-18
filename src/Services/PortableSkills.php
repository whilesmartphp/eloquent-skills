<?php

namespace Whilesmart\Skills\Services;

use Exception;
use Whilesmart\Skillpack\Loader;
use Whilesmart\Skillpack\Problem;
use Whilesmart\Skillpack\Skill as PortableSkill;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Exceptions\SkillsException;
use Whilesmart\Skills\Support\FileBundle;

class PortableSkills
{
    public function __construct(protected readonly Loader $loader) {}

    /**
     * @param  array<string, string>  $files
     */
    public function inspect(array $files, array $provenance = []): PortableSkill
    {
        try {
            $skills = array_values($this->loader->fromFiles($files, $provenance)->all());
        } catch (SkillsException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw InvalidSkillBundle::unreadable($exception->getMessage());
        }

        if (count($skills) !== 1) {
            throw InvalidSkillBundle::notSingle();
        }

        return $skills[0];
    }

    /**
     * @param  array<string, string>  $files
     */
    public function forFolder(array $files, string $folder, array $provenance = []): PortableSkill
    {
        return $this->inspect(FileBundle::prefixed($files, $folder), $provenance);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function problems(PortableSkill $skill): array
    {
        return array_values(array_map(fn (Problem $problem) => $problem->toArray(), $skill->validate()));
    }

    /**
     * @param  list<array<string, mixed>>  $problems
     */
    public function hasErrors(array $problems): bool
    {
        foreach ($problems as $problem) {
            if (($problem['severity'] ?? null) === 'error') {
                return true;
            }
        }

        return false;
    }
}
