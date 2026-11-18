<?php

use Whilesmart\Skills\Authorization\ActorBindingAuthorizer;
use Whilesmart\Skills\Authorization\OwnerAccessSkillAuthorizer;
use Whilesmart\Skills\Http\Controllers\SkillController;
use Whilesmart\Skills\Http\Controllers\SkillImportController;
use Whilesmart\Skills\Http\Controllers\SkillSourceController;
use Whilesmart\Skills\Http\Controllers\SkillVersionController;
use Whilesmart\Skills\Http\Formatters\DefaultResponseFormatter;
use Whilesmart\Skills\Http\Requests\PublishSkillRequest;
use Whilesmart\Skills\Http\Requests\RevokeSkillVersionRequest;
use Whilesmart\Skills\Http\Requests\StoreSkillImportRequest;
use Whilesmart\Skills\Http\Requests\StoreSkillRequest;
use Whilesmart\Skills\Http\Requests\UpdateSkillRequest;
use Whilesmart\Skills\Http\Requests\ValidateSkillFilesRequest;
use Whilesmart\Skills\Http\Resources\SkillImportResource;
use Whilesmart\Skills\Http\Resources\SkillResource;
use Whilesmart\Skills\Http\Resources\SkillVersionResource;
use Whilesmart\Skills\Imports\LocalPathSourceProvider;
use Whilesmart\Skills\Imports\UrlSourceProvider;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillBinding;
use Whilesmart\Skills\Models\SkillImport;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Models\SkillVersionAvailability;

return [
    'register_routes' => env('SKILLS_REGISTER_ROUTES', true),
    'route_prefix' => env('SKILLS_ROUTE_PREFIX', 'api'),
    'route_name_prefix' => 'skills.',
    'route_middleware' => ['api', 'auth:sanctum'],
    'write_middleware' => [],
    'route_groups' => ['skills' => true, 'versions' => true, 'imports' => true, 'sources' => true],
    'disabled_routes' => [],

    'default_scope_key' => 'default',

    'tables' => [
        'skills' => 'skills',
        'versions' => 'skill_versions',
        'availabilities' => 'skill_version_availabilities',
        'imports' => 'skill_imports',
        'bindings' => 'skill_bindings',
    ],

    'models' => [
        'skill' => Skill::class,
        'version' => SkillVersion::class,
        'availability' => SkillVersionAvailability::class,
        'import' => SkillImport::class,
        'binding' => SkillBinding::class,
    ],

    'authorizer' => OwnerAccessSkillAuthorizer::class,
    'binding_authorizer' => ActorBindingAuthorizer::class,
    'response_formatter' => DefaultResponseFormatter::class,

    'controllers' => [
        'skills' => SkillController::class,
        'versions' => SkillVersionController::class,
        'imports' => SkillImportController::class,
        'sources' => SkillSourceController::class,
    ],

    'requests' => [
        'store' => StoreSkillRequest::class,
        'update' => UpdateSkillRequest::class,
        'publish' => PublishSkillRequest::class,
        'validate' => ValidateSkillFilesRequest::class,
        'revoke' => RevokeSkillVersionRequest::class,
        'import' => StoreSkillImportRequest::class,
    ],

    'resources' => [
        'skill' => SkillResource::class,
        'version' => SkillVersionResource::class,
        'import' => SkillImportResource::class,
    ],

    'limits' => [
        'max_files' => (int) env('SKILLS_MAX_FILES', 200),
        'max_file_bytes' => (int) env('SKILLS_MAX_FILE_BYTES', 1024 * 1024),
        'max_bundle_bytes' => (int) env('SKILLS_MAX_BUNDLE_BYTES', 5 * 1024 * 1024),
        'max_skills_per_import' => (int) env('SKILLS_MAX_SKILLS_PER_IMPORT', 25),
        'max_download_bytes' => (int) env('SKILLS_MAX_DOWNLOAD_BYTES', 8 * 1024 * 1024),
        'max_extracted_bytes' => (int) env('SKILLS_MAX_EXTRACTED_BYTES', 32 * 1024 * 1024),
        'timeout_seconds' => (int) env('SKILLS_IMPORT_TIMEOUT', 15),
        'per_page' => 100,
    ],

    'verify_content_hash' => true,

    'imports' => [
        'enabled' => env('SKILLS_IMPORTS_ENABLED', false),
        'sources' => [
            'url' => [
                'provider' => UrlSourceProvider::class,
                'enabled' => false,
                'allowed_hosts' => [],
                'allow_http' => false,
            ],
            'local' => [
                'provider' => LocalPathSourceProvider::class,
                'enabled' => false,
                'roots' => [],
            ],
        ],
    ],
];
