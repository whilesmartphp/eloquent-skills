<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Skillpack\Pack;
use Whilesmart\Skills\Events\SkillCreated;
use Whilesmart\Skills\Events\SkillVersionPublished;
use Whilesmart\Skills\Events\SkillVersionRevoked;
use Whilesmart\Skills\Exceptions\ImmutableSkillVersion;
use Whilesmart\Skills\Exceptions\SkillAccessDenied;
use Whilesmart\Skills\Exceptions\SkillVersionUnavailable;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Services\SkillPublisher;
use Whilesmart\Skills\Services\SkillVersionResolver;
use Whilesmart\Skills\Sources\ManagedSkillSource;

class SkillVersionTest extends TestCase
{
    #[Test]
    public function revoked_versions_cannot_be_read_or_converted(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $version = $this->publishedVersion($user, $workspace);
        $url = "/api/skills/{$version->skill->uuid}/versions/{$version->uuid}";
        $this->actingAs($user)->postJson($url.'/revoke')->assertOk();
        $this->getJson($url)->assertNotFound();
        $this->expectException(SkillVersionUnavailable::class);
        $version->toPortableSkill();
    }

    #[Test]
    public function publishing_creates_an_immutable_validated_version(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);

        $version = $this->publishedVersion($user, $workspace);

        $this->assertSame(1, $version->number);
        $this->assertSame('page-layout', $version->name);
        $this->assertSame('page-layout', $version->frontmatter['name']);
        $this->assertStringContainsString('# Page layout', $version->body);
        $this->assertSame($this->fixture('page-layout'), $version->files);
        $this->assertTrue($version->isAvailable());
        $this->assertSame($version->skill->uuid, $version->provenance['catalogue']['skill']);

        $portable = $version->toPortableSkill();
        $this->assertSame('page-layout', $portable->name());
        $this->assertSame($version->content_hash, $portable->contentHash());
        $this->assertStringContainsString('12 column grid', $portable->reference('grids'));
        $this->assertSame(['primary-action', 'tap-targets'], array_column(array_map(fn ($check) => (array) $check, $portable->checks()), 'id'));

        $this->expectException(ImmutableSkillVersion::class);
        $version->forceFill(['body' => 'changed'])->save();
    }

    #[Test]
    public function versions_cannot_be_deleted(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $version = $this->publishedVersion($user, $workspace);

        $this->expectException(ImmutableSkillVersion::class);
        $version->delete();
    }

    #[Test]
    public function publishing_is_revision_checked_and_rejects_invalid_skills(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);

        $id = $this->createSkillVia($user, $workspace)->json('data.id');
        $this->actingAs($user)->patchJson("/api/skills/{$id}", ['draft_revision' => 1, 'metadata' => ['a' => 1]])->assertOk();
        $this->actingAs($user)->postJson("/api/skills/{$id}/publish", ['draft_revision' => 1])->assertStatus(409);
        $this->actingAs($user)->postJson("/api/skills/{$id}/publish", ['draft_revision' => 2])
            ->assertCreated()
            ->assertJsonPath('data.number', 1)
            ->assertJsonPath('data.available', true);
        $this->actingAs($user)->postJson("/api/skills/{$id}/publish", ['draft_revision' => 2])
            ->assertCreated()
            ->assertJsonPath('data.number', 2);

        $bad = $this->createSkillVia($user, $workspace, 'bad-skill')->json('data.id');
        $response = $this->actingAs($user)->postJson("/api/skills/{$bad}/publish", ['draft_revision' => 1])->assertUnprocessable();
        $this->assertContains('description-brackets', array_column($response->json('errors.problems'), 'code'));

        $this->assertSame(2, SkillVersion::query()->count());
        $this->actingAs($user)->getJson("/api/skills/{$id}/versions")->assertOk()->assertJsonPath('data.meta.total', 2)->assertJsonPath('data.data.0.number', 2);
    }

    #[Test]
    public function a_published_skill_cannot_be_renamed(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $version = $this->publishedVersion($user, $workspace);
        $skill = $version->skill;

        $this->actingAs($user)->patchJson("/api/skills/{$skill->uuid}", [
            'draft_revision' => 1,
            'files' => $this->payloadFiles($this->fixture('data-modelling')),
        ])->assertUnprocessable();
    }

    #[Test]
    public function revocation_is_separate_state_and_fails_closed_everywhere(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $version = $this->publishedVersion($user, $workspace);
        $skillId = $version->skill->uuid;

        $this->actingAs($user)->postJson("/api/skills/{$skillId}/versions/{$version->uuid}/revoke", ['reason' => 'Superseded'])
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.availability.status', 'revoked')
            ->assertJsonPath('data.availability.reason', 'Superseded');

        $this->assertFalse($version->fresh()->isAvailable());
        $this->assertSame($version->content_hash, $version->fresh()->content_hash);

        $this->expectException(SkillVersionUnavailable::class);
        app(SkillVersionResolver::class)->resolve($version->uuid, $workspace->getMorphClass(), $workspace->getKey(), 'default', $user);
    }

    #[Test]
    public function the_resolver_enforces_owner_scope_actor_and_deletion(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $other = $this->workspace('Other');
        $this->grant($user, $workspace);
        $this->grant($user, $other);
        $version = $this->publishedVersion($user, $workspace);
        $resolver = app(SkillVersionResolver::class);
        $type = $workspace->getMorphClass();

        $this->assertTrue($resolver->resolve($version->uuid, $type, $workspace->getKey(), 'default', $user)->is($version));
        $this->assertTrue($resolver->resolve($version->uuid, $type, (string) $workspace->getKey(), 'default', $user)->is($version));

        foreach ([
            fn () => $resolver->resolve($version->uuid, $type, $other->getKey(), 'default', $user),
            fn () => $resolver->resolve($version->uuid, $type, $workspace->getKey(), 'staging', $user),
            fn () => $resolver->resolve('not-a-uuid', $type, $workspace->getKey(), 'default', $user),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('An out of scope version resolved.');
            } catch (SkillVersionUnavailable|SkillAccessDenied) {
            }
        }

        try {
            $resolver->resolve($version->uuid, $type, $workspace->getKey(), 'default', $this->user('Stranger'));
            $this->fail('An unauthorized actor resolved a version.');
        } catch (SkillAccessDenied) {
        }

        $this->actingAs($user)->deleteJson("/api/skills/{$version->skill->uuid}")->assertOk();

        $this->assertFalse($version->fresh()->isAvailable());
        $this->expectException(SkillVersionUnavailable::class);
        $resolver->resolve($version->uuid, $type, $workspace->getKey(), 'default', $user);
    }

    #[Test]
    public function the_managed_source_returns_a_pack_of_pinned_versions(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $layout = $this->publishedVersion($user, $workspace);
        $modelling = $this->publishedVersion($user, $workspace, 'data-modelling');

        $pack = (new ManagedSkillSource($workspace->getMorphClass(), $workspace->getKey(), 'default', [$layout->uuid, $modelling->getKey()], $user))->load();

        $this->assertInstanceOf(Pack::class, $pack);
        $this->assertSame(['page-layout', 'data-modelling'], array_column($pack->index(), 'name'));
        $this->assertSame('data-modelling', $pack->get('data-modelling')->name());

        app(SkillPublisher::class)->revoke($modelling, null, $user);

        $this->expectException(SkillVersionUnavailable::class);
        (new ManagedSkillSource($workspace->getMorphClass(), $workspace->getKey(), 'default', [$layout->uuid, $modelling->uuid], $user))->load();
    }

    #[Test]
    public function tampered_content_is_refused(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $version = $this->publishedVersion($user, $workspace);

        DB::table('skill_versions')->where('id', $version->getKey())->update(['content_hash' => str_repeat('0', 64)]);

        $this->expectException(SkillVersionUnavailable::class);
        $version->fresh()->toPortableSkill();
    }

    #[Test]
    public function domain_events_are_dispatched(): void
    {
        Event::fake([SkillCreated::class, SkillVersionPublished::class, SkillVersionRevoked::class]);
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);

        $version = $this->publishedVersion($user, $workspace);
        $this->actingAs($user)->postJson("/api/skills/{$version->skill->uuid}/versions/{$version->uuid}/revoke")->assertOk();
        $this->actingAs($user)->postJson("/api/skills/{$version->skill->uuid}/versions/{$version->uuid}/revoke")->assertOk();

        Event::assertDispatchedTimes(SkillCreated::class, 1);
        Event::assertDispatched(SkillVersionPublished::class, fn (SkillVersionPublished $event) => $event->version->is($version) && $event->actor?->is($user));
        Event::assertDispatchedTimes(SkillVersionRevoked::class, 1);
    }
}
