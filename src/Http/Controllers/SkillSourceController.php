<?php

namespace Whilesmart\Skills\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Whilesmart\Skills\Http\Controllers\Concerns\HandlesSkillRequests;
use Whilesmart\Skills\Imports\SkillSourceRegistry;

class SkillSourceController extends Controller
{
    use HandlesSkillRequests;

    public function index(SkillSourceRegistry $sources): JsonResponse
    {
        $listed = [];

        foreach ($sources->available() as $key => $provider) {
            $listed[] = ['key' => $key, ...$provider->describe()];
        }

        return $this->respond($listed);
    }
}
