<?php

namespace Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Skills\Http\Formatters\DefaultResponseFormatter;
use Whilesmart\Skills\Http\Requests\StoreSkillRequest;
use Whilesmart\Skills\Http\Resources\SkillResource;

class CustomizationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('skills.route_groups.sources', false);
        $app['config']->set('skills.disabled_routes', ['imports.index']);
    }

    #[Test]
    public function route_groups_and_actions_can_be_disabled(): void
    {
        $this->getJson('/api/skill-sources')->assertNotFound();
        $this->getJson('/api/skill-imports')->assertStatus(405);
        $this->actingAs($this->user())->getJson('/api/skills')->assertOk();
    }

    #[Test]
    public function configured_resources_and_formatters_are_used_by_http(): void
    {
        config(['skills.resources.skill' => HostSkillResource::class, 'skills.response_formatter' => HostFormatter::class]);
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $this->createSkillVia($user, $workspace)->assertCreated()->assertJsonPath('host', true)->assertJsonPath('data.host_label', 'page-layout');
    }

    #[Test]
    public function configured_requests_validate_the_real_upload(): void
    {
        config(['skills.requests.store' => HostStoreSkillRequest::class]);
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $this->createSkillVia($user, $workspace)->assertUnprocessable()->assertJsonValidationErrors('host_reference');
    }
}

class HostSkillResource extends SkillResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['host_label' => $this->name];
    }
}

class HostFormatter extends DefaultResponseFormatter
{
    public function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(['host' => true, 'data' => $data], $status);
    }
}

class HostStoreSkillRequest extends StoreSkillRequest
{
    public function rules(): array
    {
        return parent::rules() + ['host_reference' => ['required', 'string']];
    }
}
