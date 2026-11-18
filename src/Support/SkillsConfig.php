<?php

namespace Whilesmart\Skills\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Whilesmart\Skills\Http\Controllers\SkillController;
use Whilesmart\Skills\Http\Controllers\SkillImportController;
use Whilesmart\Skills\Http\Controllers\SkillSourceController;
use Whilesmart\Skills\Http\Controllers\SkillVersionController;
use Whilesmart\Skills\Http\Requests\PublishSkillRequest;
use Whilesmart\Skills\Http\Requests\RevokeSkillVersionRequest;
use Whilesmart\Skills\Http\Requests\StoreSkillImportRequest;
use Whilesmart\Skills\Http\Requests\StoreSkillRequest;
use Whilesmart\Skills\Http\Requests\UpdateSkillRequest;
use Whilesmart\Skills\Http\Requests\ValidateSkillFilesRequest;
use Whilesmart\Skills\Http\Resources\SkillImportResource;
use Whilesmart\Skills\Http\Resources\SkillResource;
use Whilesmart\Skills\Http\Resources\SkillVersionResource;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillBinding;
use Whilesmart\Skills\Models\SkillImport;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Models\SkillVersionAvailability;

final class SkillsConfig
{
    public const NAME_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public const SCOPE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/';

    private const MODELS = [
        'skill' => Skill::class,
        'version' => SkillVersion::class,
        'availability' => SkillVersionAvailability::class,
        'import' => SkillImport::class,
        'binding' => SkillBinding::class,
    ];

    private const TABLES = [
        'skills' => 'skills',
        'versions' => 'skill_versions',
        'availabilities' => 'skill_version_availabilities',
        'imports' => 'skill_imports',
        'bindings' => 'skill_bindings',
    ];

    private const CONTROLLERS = [
        'skills' => SkillController::class,
        'versions' => SkillVersionController::class,
        'imports' => SkillImportController::class,
        'sources' => SkillSourceController::class,
    ];

    private const REQUESTS = [
        'store' => StoreSkillRequest::class,
        'update' => UpdateSkillRequest::class,
        'publish' => PublishSkillRequest::class,
        'validate' => ValidateSkillFilesRequest::class,
        'revoke' => RevokeSkillVersionRequest::class,
        'import' => StoreSkillImportRequest::class,
    ];

    private const RESOURCES = [
        'skill' => SkillResource::class,
        'version' => SkillVersionResource::class,
        'import' => SkillImportResource::class,
    ];

    private const LIMITS = [
        'max_files' => 200,
        'max_file_bytes' => 1048576,
        'max_bundle_bytes' => 5242880,
        'max_skills_per_import' => 25,
        'max_download_bytes' => 8388608,
        'max_extracted_bytes' => 33554432,
        'timeout_seconds' => 15,
        'per_page' => 100,
    ];

    /**
     * @return class-string<Model>
     */
    public static function model(string $key): string
    {
        $class = config("skills.models.$key") ?: (self::MODELS[$key] ?? null);

        if (! is_string($class) || ! is_a($class, self::MODELS[$key] ?? Model::class, true)) {
            throw new InvalidArgumentException("The skills model [$key] must extend ".(self::MODELS[$key] ?? Model::class).'.');
        }

        return $class;
    }

    public static function query(string $key): Builder
    {
        return self::model($key)::query();
    }

    public static function table(string $key): string
    {
        $table = config("skills.tables.$key") ?: (self::TABLES[$key] ?? null);

        if (! is_string($table) || $table === '') {
            throw new InvalidArgumentException("Unknown skills table [$key].");
        }

        return $table;
    }

    public static function controller(string $key): string
    {
        return self::configured('controllers', $key, self::CONTROLLERS);
    }

    public static function request(string $key): string
    {
        return self::configured('requests', $key, self::REQUESTS);
    }

    public static function resource(string $key): string
    {
        return self::configured('resources', $key, self::RESOURCES);
    }

    public static function limit(string $key): int
    {
        return max(1, (int) (config("skills.limits.$key") ?: (self::LIMITS[$key] ?? 1)));
    }

    public static function defaultScopeKey(): string
    {
        $scope = config('skills.default_scope_key', 'default');

        return is_string($scope) && preg_match(self::SCOPE_PATTERN, $scope) ? $scope : 'default';
    }

    public static function scopeKey(mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::defaultScopeKey();
        }

        if (! is_string($value) || ! preg_match(self::SCOPE_PATTERN, $value)) {
            throw new InvalidArgumentException('The scope key is not valid.');
        }

        return $value;
    }

    public static function validName(string $name): bool
    {
        return strlen($name) <= 64 && preg_match(self::NAME_PATTERN, $name) === 1;
    }

    private static function configured(string $group, string $key, array $defaults): string
    {
        $class = config("skills.$group.$key") ?: ($defaults[$key] ?? null);

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("The skills $group entry [$key] is not a class.");
        }

        if (! is_a($class, $defaults[$key], true)) {
            throw new InvalidArgumentException("The skills $group entry [$key] must extend ".$defaults[$key].'.');
        }

        return $class;
    }
}
