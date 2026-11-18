<?php

namespace Whilesmart\Skills\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Http\Controllers\Concerns\HandlesSkillRequests;
use Whilesmart\Skills\Models\SkillImport;
use Whilesmart\Skills\Services\SkillImporter;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillImportController extends Controller
{
    use HandlesSkillRequests;

    public function __construct(protected readonly SkillImporter $importer) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->authorizer()->scope(SkillsConfig::query('import'), $request->user(), SkillAuthorizer::VIEW);
        $this->filterOwner($query, $request);

        if ($request->filled('status') && is_string($request->input('status'))) {
            $query->where('status', $request->input('status'));
        }

        return $this->paginated('import', $query->orderByDesc('created_at')->orderByDesc('id'), $request);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->formRequest('import');

        $import = $this->importer->import(
            (string) $form->input('owner_type'),
            $form->input('owner_id'),
            $form->input('scope_key'),
            (string) $form->input('source'),
            (array) $form->input('input', []),
            $form->boolean('update_existing'),
            $request->user(),
        );

        $resource = $this->resource('import', $import);

        if ($import->status !== SkillImport::COMPLETED) {
            return $this->formatter()->error(
                (string) ($import->error ?? 'The import failed.'),
                422,
                ['import' => $resource->resolve($request)],
            );
        }

        return $this->respond($resource, 201);
    }

    public function show(Request $request, string $import): JsonResponse
    {
        $model = Str::isUuid($import) ? SkillsConfig::query('import')->where('uuid', $import)->first() : null;

        if (! $model instanceof SkillImport) {
            throw new NotFoundHttpException;
        }

        $this->authorizeOwner($model, $request->user(), SkillAuthorizer::VIEW);

        return $this->respond($this->resource('import', $model));
    }
}
