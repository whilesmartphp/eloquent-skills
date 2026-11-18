<?php

namespace Whilesmart\Skills\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Http\Controllers\Concerns\HandlesSkillRequests;
use Whilesmart\Skills\Services\SkillDrafts;
use Whilesmart\Skills\Services\SkillPublisher;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillController extends Controller
{
    use HandlesSkillRequests;

    public function __construct(
        protected readonly SkillDrafts $drafts,
        protected readonly SkillPublisher $publisher,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->authorizer()->scope(SkillsConfig::query('skill'), $request->user(), SkillAuthorizer::VIEW);
        $this->filterOwner($query, $request);

        if ($request->filled('q') && is_string($request->input('q'))) {
            $term = '%'.mb_strtolower(mb_substr($request->input('q'), 0, 100)).'%';
            $query->where(fn ($inner) => $inner
                ->whereRaw('lower(name) like ?', [$term])
                ->orWhereRaw('lower(description) like ?', [$term]));
        }

        if ($request->boolean('published')) {
            $query->where('latest_version_number', '>', 0);
        }

        return $this->paginated('skill', $query->orderByDesc('updated_at')->orderByDesc('id'), $request);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->formRequest('store');

        $skill = $this->drafts->create(
            (string) $form->input('owner_type'),
            $form->input('owner_id'),
            $form->input('scope_key'),
            $form->skillFiles(),
            $form->input('metadata'),
            $request->user(),
        );

        return $this->respond($this->resource('skill', $skill, true), 201);
    }

    public function show(Request $request, string $skill): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::VIEW);

        return $this->respond($this->resource('skill', $model, true));
    }

    public function update(Request $request, string $skill): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::UPDATE);
        $form = $this->formRequest('update');

        $updated = $this->drafts->update(
            $model,
            (int) $form->input('draft_revision'),
            $form->hasSkillFiles() ? $form->skillFiles() : null,
            $form->exists('metadata') ? ($form->input('metadata') ?? []) : null,
            $request->user(),
        );

        return $this->respond($this->resource('skill', $updated, true));
    }

    public function destroy(Request $request, string $skill): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::DELETE);
        $this->drafts->delete($model, $request->user());

        return $this->respond(null);
    }

    public function validation(Request $request, string $skill): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::VIEW);
        $problems = $this->drafts->problems($model);

        return $this->respond([
            'name' => $model->name,
            'draft_revision' => $model->draft_revision,
            'valid' => ! $this->hasErrors($problems),
            'problems' => $problems,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $form = $this->formRequest('validate');
        $report = $this->drafts->check($form->skillFiles());

        return $this->respond([
            'name' => $report['name'],
            'valid' => ! $this->hasErrors($report['problems']),
            'problems' => $report['problems'],
        ]);
    }

    public function publish(Request $request, string $skill): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::PUBLISH);
        $form = $this->formRequest('publish');

        $version = $this->publisher->publish($model, (int) $form->input('draft_revision'), $request->user());
        $version->load(['skill', 'availability']);

        return $this->respond($this->resource('version', $version, true), 201);
    }

    protected function hasErrors(array $problems): bool
    {
        foreach ($problems as $problem) {
            if (($problem['severity'] ?? null) === 'error') {
                return true;
            }
        }

        return false;
    }
}
