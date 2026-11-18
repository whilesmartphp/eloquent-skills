<?php

namespace Whilesmart\Skills\Http\Controllers\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Whilesmart\Skills\Contracts\ResponseFormatter;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Support\SkillsConfig;

trait HandlesSkillRequests
{
    protected function formatter(): ResponseFormatter
    {
        return app(ResponseFormatter::class);
    }

    protected function respond(mixed $data = null, int $status = 200): JsonResponse
    {
        return $this->formatter()->success($data, $status);
    }

    protected function formRequest(string $key): FormRequest
    {
        $class = SkillsConfig::request($key);

        if (! is_a($class, FormRequest::class, true)) {
            throw new LogicException("The skills request [$key] must extend ".FormRequest::class.'.');
        }

        return app($class);
    }

    protected function resource(string $key, Model $model, bool $withFiles = false): JsonResource
    {
        $class = SkillsConfig::resource($key);
        $resource = new $class($model);

        if ($withFiles && method_exists($resource, 'withFiles')) {
            $resource->withFiles();
        }

        return $resource;
    }

    protected function paginated(string $key, Builder $query, Request $request): JsonResponse
    {
        $perPage = min(SkillsConfig::limit('per_page'), max(1, (int) $request->input('per_page', 25)));
        $paginator = $query->paginate($perPage);
        $class = SkillsConfig::resource($key);

        return $this->respond($class::collection($paginator)->response()->getData(true));
    }

    protected function authorizer(): SkillAuthorizer
    {
        return app(SkillAuthorizer::class);
    }

    protected function authorizeSkill(Skill $skill, ?Authenticatable $actor, string $ability): void
    {
        if (! $this->authorizer()->authorize($actor, $ability, $skill->owner_type, $skill->owner_id, $skill->scope_key)) {
            throw new AccessDeniedHttpException;
        }
    }

    protected function authorizeOwner(Model $record, ?Authenticatable $actor, string $ability): void
    {
        if (! $this->authorizer()->authorize($actor, $ability, (string) $record->getAttribute('owner_type'), (string) $record->getAttribute('owner_id'), (string) $record->getAttribute('scope_key'))) {
            throw new AccessDeniedHttpException;
        }
    }

    protected function findSkill(string $publicId): Skill
    {
        $skill = Str::isUuid($publicId) ? SkillsConfig::query('skill')->where('public_id', $publicId)->first() : null;

        if (! $skill instanceof Skill) {
            throw new NotFoundHttpException;
        }

        return $skill;
    }

    protected function findVersion(Skill $skill, string $publicId): SkillVersion
    {
        $version = Str::isUuid($publicId)
            ? SkillsConfig::query('version')->where('skill_id', $skill->getKey())->where('public_id', $publicId)->with(['skill', 'availability'])->first()
            : null;

        if (! $version instanceof SkillVersion) {
            throw new NotFoundHttpException;
        }

        return $version;
    }

    protected function filterOwner(Builder $query, Request $request): Builder
    {
        $table = $query->getModel()->getTable();

        if ($request->filled('owner_type') && $request->filled('owner_id')) {
            $query->where($table.'.owner_type', (string) $request->input('owner_type'))
                ->where($table.'.owner_id', (string) $request->input('owner_id'));
        }

        if ($request->filled('scope_key')) {
            $query->where($table.'.scope_key', (string) $request->input('scope_key'));
        }

        return $query;
    }
}
