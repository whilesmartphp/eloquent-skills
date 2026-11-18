<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Models\AgentProfile;
use Tests\Support\Models\UuidUser;
use Tests\Support\Models\UuidWorkspace;
use Tests\TestCase;
use Whilesmart\Skills\Contracts\BindingAuthorizer;
use Whilesmart\Skills\Exceptions\SkillAccessDenied;
use Whilesmart\Skills\Exceptions\SkillBindingRejected;
use Whilesmart\Skills\Exceptions\SkillVersionUnavailable;
use Whilesmart\Skills\Models\SkillBinding;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Services\SkillPublisher;

class HasSkillsTest extends TestCase
{
    #[Test]
    public function a_workspace_binds_its_own_skill_version(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $version = $this->publishedVersion($user, $workspace);

        $binding = $workspace->assignSkillVersion($version, $user);

        $this->assertSame($workspace->getMorphClass(), $binding->skillable_type);
        $this->assertSame((string) $workspace->getKey(), $binding->skillable_id);
        $this->assertSame($version->getKey(), (int) $binding->skill_version_id);
        $this->assertTrue($binding->isAvailable());
        $this->assertSame(['page-layout'], $workspace->skills()->pluck('name')->all());
        $this->assertSame((int) $version->getKey(), (int) $workspace->skills()->first()->pivot->skill_version_id);
        $this->assertSame([$version->skill->uuid => $version->uuid], $workspace->pinnedSkillVersionIds());
    }

    #[Test]
    public function reassignment_moves_the_pin_without_duplicating_the_binding(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $first = $this->publishedVersion($user, $workspace);
        $skillId = $first->skill->uuid;
        $second = $this->actingAs($user)->postJson("/api/skills/{$skillId}/publish", ['draft_revision' => 1])->assertCreated()->json('data.id');
        $second = SkillVersion::query()->where('uuid', $second)->firstOrFail();

        $workspace->assignSkillVersion($first, $user);
        $workspace->assignSkillVersion($second, $user);

        $this->assertSame(1, SkillBinding::query()->count());
        $this->assertSame($second->getKey(), (int) $workspace->skillBindings()->first()->skill_version_id);

        $this->assertTrue($workspace->detachSkill($first->skill, $user));
        $this->assertSame(0, $workspace->skillBindings()->count());
    }

    #[Test]
    public function a_workspace_cannot_bind_another_owners_skill(): void
    {
        $user = $this->user();
        $mine = $this->workspace('Mine');
        $theirs = $this->workspace('Theirs');
        $this->grant($user, $mine);
        $this->grant($user, $theirs);
        $version = $this->publishedVersion($user, $theirs);

        $this->expectException(SkillBindingRejected::class);
        $mine->assignSkillVersion($version, $user);
    }

    #[Test]
    public function a_profile_binds_skills_matching_its_owner_and_scope(): void
    {
        $user = UuidUser::create(['name' => 'Uuid']);
        $workspace = UuidWorkspace::create(['name' => 'Studio']);
        $this->grant($user, $workspace);
        $default = $this->publishedVersion($user, $workspace);
        $staging = $this->publishedVersion($user, $workspace, 'data-modelling', 'staging');

        $profile = AgentProfile::create([
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'scope_key' => 'staging',
            'name' => 'Planner',
        ]);

        config(['skills.default_scope_key' => 'staging']);
        $binding = $profile->assignSkillVersion($staging, $user);
        $this->assertSame($profile->getMorphClass(), $binding->skillable_type);
        $this->assertSame(['data-modelling'], $profile->skills()->pluck('name')->all());

        try {
            $profile->assignSkillVersion($default, $user);
            $this->fail('A profile bound a skill from another scope.');
        } catch (SkillBindingRejected) {
        }

        $this->assertSame(1, $profile->skillBindings()->count());
    }

    #[Test]
    public function revoked_and_deleted_versions_cannot_be_bound_and_bindings_report_it(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $layout = $this->publishedVersion($user, $workspace);
        $modelling = $this->publishedVersion($user, $workspace, 'data-modelling');

        $binding = $workspace->assignSkillVersion($layout, $user);
        app(SkillPublisher::class)->revoke($layout, 'Withdrawn', $user);
        $this->assertFalse($binding->fresh()->isAvailable());

        try {
            $workspace->assignSkillVersion($layout, $user);
            $this->fail('A revoked version was bound.');
        } catch (SkillVersionUnavailable) {
        }

        $this->actingAs($user)->deleteJson("/api/skills/{$modelling->skill->uuid}")->assertOk();

        $this->expectException(SkillVersionUnavailable::class);
        $workspace->assignSkillVersion($modelling, $user);
    }

    #[Test]
    public function binding_requires_actor_authorization(): void
    {
        $owner = $this->user('Owner');
        $stranger = $this->user('Stranger');
        $workspace = $this->workspace();
        $this->grant($owner, $workspace);
        $version = $this->publishedVersion($owner, $workspace);

        try {
            $workspace->assignSkillVersion($version, $stranger);
            $this->fail('A stranger bound a skill.');
        } catch (SkillAccessDenied) {
        }

        try {
            $workspace->assignSkillVersion($version);
            $this->fail('A binding without an actor was allowed by the default authorizer.');
        } catch (SkillAccessDenied) {
        }

        $this->assertSame(0, SkillBinding::query()->count());
    }

    #[Test]
    public function the_binding_authorizer_is_configurable_but_cannot_bypass_owner_scope(): void
    {
        $this->app->instance(BindingAuthorizer::class, new class implements BindingAuthorizer
        {
            public function authorize(Model $skillable, SkillVersion $version, ?Authenticatable $actor): bool
            {
                return true;
            }
        });

        $user = $this->user();
        $mine = $this->workspace('Mine');
        $theirs = $this->workspace('Theirs');
        $this->grant($user, $mine);
        $this->grant($user, $theirs);
        $own = $this->publishedVersion($user, $mine);
        $foreign = $this->publishedVersion($user, $theirs, 'data-modelling');

        $this->assertInstanceOf(SkillBinding::class, $mine->assignSkillVersion($own));

        $this->expectException(SkillBindingRejected::class);
        $mine->assignSkillVersion($foreign);
    }
}
