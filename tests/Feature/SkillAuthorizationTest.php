<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Models\UuidUser;
use Tests\Support\Models\UuidWorkspace;
use Tests\TestCase;
use Whilesmart\OwnerAccess\Authorizers\AllowAllAuthorizer;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;
use Whilesmart\Skills\Contracts\SkillAuthorizer;

class SkillAuthorizationTest extends TestCase
{
    #[Test]
    public function the_permissive_owner_access_default_fails_closed(): void
    {
        $this->app->instance(OwnerAuthorizer::class, new AllowAllAuthorizer);
        $user = $this->user();
        $workspace = $this->workspace();

        $this->createSkillVia($user, $workspace)->assertForbidden();
        $this->assertDatabaseCount('skills', 0);

        $this->actingAs($user)->getJson('/api/skills')->assertOk()->assertJsonPath('data.meta.total', 0);
    }

    #[Test]
    public function an_unbound_owner_authorizer_fails_closed(): void
    {
        $this->app->offsetUnset(OwnerAuthorizer::class);

        $this->assertFalse(app(SkillAuthorizer::class)->authorize($this->user(), SkillAuthorizer::VIEW, 'any', 1, 'default'));
    }

    #[Test]
    public function a_host_skill_authorizer_replaces_the_default(): void
    {
        $this->app->instance(SkillAuthorizer::class, new class implements SkillAuthorizer
        {
            public function authorize(?Authenticatable $actor, string $ability, string $ownerType, int|string $ownerId, string $scopeKey): bool
            {
                return $actor !== null && $scopeKey === 'sandbox';
            }

            public function scope(Builder $query, ?Authenticatable $actor, string $ability = self::VIEW): Builder
            {
                return $query->where('scope_key', 'sandbox');
            }
        });

        $user = $this->user();
        $workspace = $this->workspace();

        $this->createSkillVia($user, $workspace)->assertForbidden();
        $this->createSkillVia($user, $workspace, 'page-layout', 'sandbox')->assertCreated();
    }

    #[Test]
    public function other_owners_cannot_read_edit_publish_revoke_or_delete(): void
    {
        $owner = $this->user('Owner');
        $intruder = $this->user('Intruder');
        $workspace = $this->workspace();
        $elsewhere = $this->workspace('Elsewhere');
        $this->grant($owner, $workspace);
        $this->grant($intruder, $elsewhere);

        $version = $this->publishedVersion($owner, $workspace);
        $skillId = $version->skill->uuid;

        $this->actingAs($intruder)->getJson("/api/skills/{$skillId}")->assertForbidden();
        $this->actingAs($intruder)->getJson("/api/skills/{$skillId}/validation")->assertForbidden();
        $this->actingAs($intruder)->getJson("/api/skills/{$skillId}/versions")->assertForbidden();
        $this->actingAs($intruder)->getJson("/api/skills/{$skillId}/versions/{$version->uuid}")->assertForbidden();
        $this->actingAs($intruder)->patchJson("/api/skills/{$skillId}", ['draft_revision' => 1, 'metadata' => ['x' => 1]])->assertForbidden();
        $this->actingAs($intruder)->postJson("/api/skills/{$skillId}/publish", ['draft_revision' => 1])->assertForbidden();
        $this->actingAs($intruder)->postJson("/api/skills/{$skillId}/versions/{$version->uuid}/revoke")->assertForbidden();
        $this->actingAs($intruder)->deleteJson("/api/skills/{$skillId}")->assertForbidden();

        $this->createSkillVia($intruder, $workspace, 'data-modelling')->assertForbidden();

        $this->assertTrue($version->fresh()->isAvailable());
        $this->assertSame(1, $this->skill($skillId)->draft_revision);
        $this->assertDatabaseCount('skill_versions', 1);
    }

    #[Test]
    public function uuid_actors_manage_uuid_owners_and_integer_actors_are_kept_out(): void
    {
        $uuidActor = UuidUser::create(['name' => 'Uuid']);
        $uuidWorkspace = UuidWorkspace::create(['name' => 'Uuid studio']);
        $intActor = $this->user('Integer');
        $intWorkspace = $this->workspace('Integer studio');
        $this->grant($uuidActor, $uuidWorkspace);
        $this->grant($intActor, $intWorkspace);

        $created = $this->createSkillVia($uuidActor, $uuidWorkspace)->assertCreated();
        $created->assertJsonPath('data.owner_id', $uuidWorkspace->getKey());
        $skillId = $created->json('data.id');

        $this->actingAs($uuidActor)->getJson("/api/skills/{$skillId}")->assertOk();
        $this->actingAs($uuidActor)->postJson("/api/skills/{$skillId}/publish", ['draft_revision' => 1])->assertCreated();

        $this->actingAs($intActor)->getJson("/api/skills/{$skillId}")->assertForbidden();
        $this->actingAs($intActor)->getJson('/api/skills')->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->createSkillVia($intActor, $uuidWorkspace, 'data-modelling')->assertForbidden();

        $this->actingAs($uuidActor)->getJson('/api/skills')->assertOk()->assertJsonPath('data.meta.total', 1);
        $this->actingAs($uuidActor)->getJson('/api/skills?owner_type='.urlencode($intWorkspace->getMorphClass()).'&owner_id='.$intWorkspace->getKey())
            ->assertOk()
            ->assertJsonPath('data.meta.total', 0);
    }

    #[Test]
    public function guests_are_denied(): void
    {
        $workspace = $this->workspace();

        $this->postJson('/api/skills', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'files' => $this->payloadFiles($this->fixture('page-layout')),
        ])->assertForbidden();

        $this->postJson('/api/skills/validate', [
            'files' => $this->payloadFiles($this->fixture('page-layout')),
        ])->assertForbidden();
    }
}
