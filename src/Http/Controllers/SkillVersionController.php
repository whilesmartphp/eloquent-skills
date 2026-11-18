<?php

namespace Whilesmart\Skills\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Whilesmart\Skills\Contracts\SkillAuthorizer;
use Whilesmart\Skills\Http\Controllers\Concerns\HandlesSkillRequests;
use Whilesmart\Skills\Services\SkillPublisher;
use Whilesmart\Skills\Support\SkillsConfig;

class SkillVersionController extends Controller
{
    use HandlesSkillRequests;

    public function __construct(protected readonly SkillPublisher $publisher) {}

    public function index(Request $request, string $skill): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::VIEW);

        $query = SkillsConfig::query('version')
            ->where('skill_id', $model->getKey())
            ->with(['skill', 'availability'])
            ->orderByDesc('number');

        if ($request->boolean('available')) {
            $query->available();
        }

        return $this->paginated('version', $query, $request);
    }

    public function show(Request $request, string $skill, string $version): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::VIEW);

        return $this->respond($this->resource('version', $this->findVersion($model, $version), true));
    }

    public function revoke(Request $request, string $skill, string $version): JsonResponse
    {
        $model = $this->findSkill($skill);
        $this->authorizeSkill($model, $request->user(), SkillAuthorizer::REVOKE);
        $found = $this->findVersion($model, $version);
        $form = $this->formRequest('revoke');

        $revoked = $this->publisher->revoke($found, $form->input('reason'), $request->user());
        $revoked->load(['skill', 'availability']);

        return $this->respond($this->resource('version', $revoked));
    }
}
