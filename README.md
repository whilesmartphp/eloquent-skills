# eloquent-skills

An owner-scoped [Agent Skills](https://agentskills.io/specification) catalogue for Laravel. Skills are
written as raw files, edited as revision-checked drafts, validated and published as immutable
versions, and pinned to any model through `HasSkills`. Loading, validation and model tools come from
the framework-independent [`whilesmart/skillpack`](https://github.com/whilesmartphp/skillpack) core.

The catalogue stores and serves skill text. It never runs a skill's scripts, never grants tools from
`allowed-tools`, and never evaluates `checks.yaml`; those decisions belong to the host.

The package is unreleased. Install the implementation branch from GitHub:

```sh
composer config repositories.skillpack vcs https://github.com/whilesmartphp/skillpack
composer config repositories.eloquent-skills vcs https://github.com/whilesmartphp/eloquent-skills
composer require whilesmart/skillpack:dev-feat/initial-package whilesmart/eloquent-skills:dev-feat/initial-package
php artisan vendor:publish --tag=skills-config
php artisan migrate
```

## Authorization fails closed

Every read and write is checked against the skill's polymorphic owner (`owner_type`, `owner_id`) and
`scope_key`. The default `OwnerAccessSkillAuthorizer` delegates to the `OwnerAuthorizer` from
`whilesmart/eloquent-owner-access`, but refuses everything while that binding is missing or is the
permissive `AllowAllAuthorizer`. Bind a real owner authorizer, or your own `SkillAuthorizer`:

```php
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;

$this->app->bind(OwnerAuthorizer::class, WorkspaceMemberAuthorizer::class);
```

```php
use Whilesmart\Skills\Contracts\SkillAuthorizer;

interface SkillAuthorizer
{
    public function authorize(?Authenticatable $actor, string $ability, string $ownerType, int|string $ownerId, string $scopeKey): bool;
    public function scope(Builder $query, ?Authenticatable $actor, string $ability = self::VIEW): Builder;
}
```

Abilities are `view`, `create`, `update`, `delete`, `publish`, `revoke`, `import`, `bind` and
`read`. The actor is any `Authenticatable`, so integer and UUID user models both work; a `null` actor is
passed through and the authorizer decides. Set `skills.authorizer` to a class name to replace the
default without a container binding.

## Owners and scopes

Owner ids are stored as strings, so integer and UUID owners mix freely. `scope_key` is a host-neutral
isolation string (default `default`, letters, digits, `.`, `_`, `:` and `-`, at most 64 characters).
Skill names are unique among live skills of one owner and scope; deleting a skill frees its name.

Public identifiers are UUIDs (`id` in every response); internal keys stay bigints.

## Drafts over HTTP

Routes are registered under `skills.route_prefix` (default `api`) with `skills.route_middleware`
(default `api`, `auth:sanctum`). Writes additionally run `skills.write_middleware`.

| Method | Path | Purpose |
|---|---|---|
| GET | `/skills` | Accessible skills, filter by `owner_type` + `owner_id`, `scope_key`, `q`, `published` |
| POST | `/skills` | Create a draft from raw files |
| GET | `/skills/{id}` | A skill with its draft files |
| PATCH/PUT | `/skills/{id}` | Edit the draft; needs the current `draft_revision` |
| DELETE | `/skills/{id}` | Soft delete; every version becomes unavailable |
| GET | `/skills/{id}/validation` | Validation problems for the draft |
| POST | `/skills/validate` | Validate files without storing them |
| POST | `/skills/{id}/publish` | Publish the draft at `draft_revision` as the next version |
| GET | `/skills/{id}/versions` | Versions, newest first (`available=1` to filter) |
| GET | `/skills/{id}/versions/{version}` | One version with its files |
| POST | `/skills/{id}/versions/{version}/revoke` | Revoke a version (`reason` optional) |
| GET | `/skill-sources` | Enabled import sources |
| GET/POST | `/skill-imports` | List or run imports |
| GET | `/skill-imports/{id}` | One import |

Files are sent as JSON. Text files are sent as is; binary files are base64 with `"encoding": "base64"`.
Content is read from the raw request body, so global string trimming does not alter it.

```json
{
  "owner_type": "workspace",
  "owner_id": 42,
  "scope_key": "default",
  "files": [
    {"path": "SKILL.md", "content": "---\nname: page-layout\ndescription: Lay out a page.\n---\n\n# Page layout\n"},
    {"path": "assets/logo.png", "content": "iVBORw0KGgo=", "encoding": "base64"}
  ]
}
```

Multipart uploads work too: `uploads[]` files with matching `paths[]`.

Paths must be relative, use `/`, and contain no empty, `.` or `..` segments, backslashes, colons or
control characters. A bundle needs `SKILL.md` at its root and is limited by `skills.limits`
(`max_files`, `max_file_bytes`, `max_bundle_bytes`). The skill's name comes from the `SKILL.md`
frontmatter and must follow the Agent Skills naming rules. A draft may be semantically invalid;
publishing is refused with the validation problems until it is not.

Every edit and publish carries the `draft_revision` it was based on. A stale revision answers `409`
with `errors.current_revision`.

## Versions and availability

Publishing validates the draft through the core, stores an immutable `SkillVersion` (files,
frontmatter, body, provenance and the core's content hash) and an active
`SkillVersionAvailability`. Versions cannot be updated or deleted. Availability is separate state:
revoking a version, or deleting its skill, makes it unavailable everywhere. A published skill keeps
its name.

```php
$version->isAvailable();       // current availability, rechecked against the database
$version->toPortableSkill();   // Whilesmart\Skillpack\Skill; refuses content that no longer matches its hash
```

Consumers must go through owner, scope and availability checks, never a raw `find`:

```php
use Whilesmart\Skills\Services\SkillVersionResolver;
use Whilesmart\Skills\Sources\ManagedSkillSource;

$version = app(SkillVersionResolver::class)->resolve($publicId, $ownerType, $ownerId, $scopeKey, $actor);

$pack = (new ManagedSkillSource($ownerType, $ownerId, $scopeKey, [$publicId, ...], $actor))->load();
$pack->tools(); // list_skills, read_skill, read_reference
```

`ManagedSkillSource` takes version public ids, or internal keys as integers, and fails when any of them
is unavailable or outside the owner and scope.

## Binding skills to models

```php
use Whilesmart\Skills\Traits\HasSkills;

class Workspace extends Model
{
    use HasSkills;
}

$workspace->assignSkillVersion($version, $actor); // SkillBinding pinned to that exact version
$workspace->skills();                             // managed skills, pivot skill_version_id
$workspace->skillBindings();
$workspace->detachSkill($skill, $actor);
$workspace->pinnedSkillVersionIds();              // [skill public id => version public id]
```

A model binds skills of the owner and scope it reports. By default a model with `owner_type` and
`owner_id` attributes (such as an agent profile) uses that owner and its `scope_key`; any other model is
its own owner in the default scope. Override `skillOwnerType()`, `skillOwnerId()` and
`skillScopeKey()` to change this.

Binding always requires the same owner and scope and a currently available version. It then asks the
configured `BindingAuthorizer` (`skills.binding_authorizer`), whose default checks the `bind` ability
for the actor. Assigning again moves the pin; bindings are unique per model and skill.

## Imports

Imports are off by default. Turn them on and enable each source explicitly:

```php
'imports' => [
    'enabled' => true,
    'sources' => [
        'url' => [
            'provider' => Whilesmart\Skills\Imports\UrlSourceProvider::class,
            'enabled' => true,
            'allowed_hosts' => ['skills.example.com'],
            'allow_http' => false,
        ],
        'local' => [
            'provider' => Whilesmart\Skills\Imports\LocalPathSourceProvider::class,
            'enabled' => true,
            'roots' => ['library' => storage_path('skills')],
        ],
    ],
],
```

```json
{"owner_type": "workspace", "owner_id": 42, "source": "local", "input": {"root": "library", "path": "page-layout"}}
{"owner_type": "workspace", "owner_id": 42, "source": "url", "input": {"url": "https://skills.example.com"}}
```

- `url` accepts https URLs (http only with `allow_http`) on exactly the listed hosts, without
  credentials. The core loader enforces the same hosts on redirects, refuses private addresses, checks
  discovery digests and applies the download, extraction, file and skill limits from `skills.limits`.
- `local` reads only folders inside a named root. Requests name the root, never a server path;
  symbolic links that leave the root are refused, and stored references, provenance and errors do not
  include server paths.

Imported skills become drafts (never published automatically). An existing name fails the import
unless `update_existing` is true. An import is all or nothing and is recorded as a `SkillImport`.

Add a source by implementing `Whilesmart\Skills\Contracts\SkillSourceProvider` and listing it under
`imports.sources`. The provider validates its own `input` keys and returns a `PreparedSource` holding a
core source (a string location or a `SkillSource`) and its `LoadOptions`.

## Customization

| Setting | Purpose |
|---|---|
| `models.*` | Subclasses of the five models |
| `tables.*` | Table names |
| `controllers.*` | Controllers used by the routes |
| `requests.*` | Form requests; extend the defaults to keep file parsing |
| `resources.*` | JSON resources |
| `response_formatter` | `Contracts\ResponseFormatter` implementation |
| `authorizer`, `binding_authorizer` | Authorization contracts |
| `register_routes`, `route_prefix`, `route_name_prefix`, `route_middleware`, `write_middleware` | Routing |
| `limits.*` | Bundle, import and pagination bounds |
| `verify_content_hash` | Check versions against their content hash when used |

Publish tags: `skills-config`, `skills-migrations`, `skills-routes`.

## Events

Dispatched after the surrounding transaction commits: `SkillCreated`, `SkillDraftUpdated`,
`SkillDeleted`, `SkillVersionPublished`, `SkillVersionRevoked`, `SkillVersionAssigned`, `SkillUnbound`
and `SkillsImported`.

## Not included

- Running scripts, checks or evals, and granting tools; the host decides what a skill may do.
- HTTP endpoints for bindings; hosts expose binding through their own models.
- Restoring a revoked version; publish a new version instead.

## Testing

```sh
composer test
composer pint:test
```

## License

MIT

Route groups (`skills`, `versions`, `imports`, `sources`) can be disabled through `route_groups`. Use `disabled_routes` to remove individual actions by their route names, such as `imports.index` or `publish`.
