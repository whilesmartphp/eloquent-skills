<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillImport;

class SkillImportTest extends TestCase
{
    private string $library;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'skills-import-'.bin2hex(random_bytes(6));
        $this->library = $base.DIRECTORY_SEPARATOR.'library';
        $this->outside = $base.DIRECTORY_SEPARATOR.'outside';

        foreach (['page-layout', 'data-modelling'] as $name) {
            File::copyDirectory(__DIR__.'/../fixtures/skills/'.$name, $this->library.DIRECTORY_SEPARATOR.$name);
        }

        File::copyDirectory(__DIR__.'/../fixtures/skills/bad-skill', $this->outside.DIRECTORY_SEPARATOR.'bad-skill');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->library));

        parent::tearDown();
    }

    private function enableLocal(): void
    {
        config()->set('skills.imports.enabled', true);
        config()->set('skills.imports.sources.local.enabled', true);
        config()->set('skills.imports.sources.local.roots', ['library' => $this->library]);
    }

    private function import(array $input, string $source = 'local', bool $update = false)
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);

        return [$user, $workspace, $this->actingAs($user)->postJson('/api/skill-imports', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'source' => $source,
            'input' => $input,
            'update_existing' => $update,
        ])];
    }

    #[Test]
    public function imports_are_disabled_by_default(): void
    {
        [, , $response] = $this->import(['root' => 'library']);

        $response->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseCount('skills', 0);
        $this->assertDatabaseCount('skill_imports', 0);
    }

    #[Test]
    public function the_source_list_exposes_root_names_not_paths(): void
    {
        $this->enableLocal();
        $user = $this->user();

        $response = $this->actingAs($user)->getJson('/api/skill-sources')->assertOk();

        $response->assertJsonPath('data.0.key', 'local')->assertJsonPath('data.0.roots', ['library']);
        $this->assertStringNotContainsString($this->library, json_encode($response->json(), JSON_UNESCAPED_SLASHES));
    }

    #[Test]
    public function it_imports_a_folder_from_a_configured_root_as_drafts(): void
    {
        $this->enableLocal();

        [$user, $workspace, $response] = $this->import(['root' => 'library', 'path' => 'page-layout']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.reference', 'library:page-layout')
            ->assertJsonPath('data.skills.0.name', 'page-layout')
            ->assertJsonPath('data.skills.0.action', 'created');

        $skill = Skill::query()->firstOrFail();
        $this->assertSame((string) $workspace->getKey(), $skill->owner_id);
        $this->assertSame(0, $skill->latest_version_number);
        $this->assertSame(file_get_contents($this->library.'/page-layout/references/grids.md'), $skill->draft_files['references/grids.md']);
        $this->assertSame('library:page-layout', $skill->draft_provenance['import']['reference']);
        $this->assertStringNotContainsString($this->library, json_encode($response->json(), JSON_UNESCAPED_SLASHES));
        $this->assertStringNotContainsString(
            $this->library,
            json_encode($this->actingAs($user)->getJson("/api/skills/{$skill->public_id}")->json(), JSON_UNESCAPED_SLASHES),
        );

        $this->actingAs($user)->getJson('/api/skill-imports')->assertOk()->assertJsonPath('data.meta.total', 1);
    }

    #[Test]
    public function importing_an_existing_name_conflicts_unless_updates_are_requested(): void
    {
        $this->enableLocal();

        [$user, $workspace] = $this->import(['root' => 'library', 'path' => 'page-layout']);

        $conflict = $this->actingAs($user)->postJson('/api/skill-imports', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'source' => 'local',
            'input' => ['root' => 'library'],
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.import.status', 'failed');

        $this->assertSame(1, Skill::query()->count());
        $this->assertSame(SkillImport::FAILED, SkillImport::query()->where('public_id', $conflict->json('errors.import.id'))->value('status'));
        $this->assertStringNotContainsString($this->library, json_encode($conflict->json(), JSON_UNESCAPED_SLASHES));

        $this->actingAs($user)->postJson('/api/skill-imports', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'source' => 'local',
            'input' => ['root' => 'library'],
            'update_existing' => true,
        ])->assertCreated();

        $this->assertSame(['data-modelling', 'page-layout'], Skill::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(2, Skill::query()->where('name', 'page-layout')->value('draft_revision'));
    }

    #[Test]
    public function it_refuses_paths_outside_configured_roots(): void
    {
        $this->enableLocal();

        foreach ([
            ['root' => 'library', 'path' => '../outside'],
            ['root' => 'library', 'path' => '/etc'],
            ['root' => 'library', 'path' => 'missing'],
            ['root' => 'elsewhere'],
            ['root' => 'library', 'path' => 'page-layout', 'extra' => 'x'],
        ] as $input) {
            [, , $response] = $this->import($input);
            $response->assertUnprocessable();
        }

        if (@symlink($this->outside, $this->library.DIRECTORY_SEPARATOR.'linked')) {
            [, , $response] = $this->import(['root' => 'library', 'path' => 'linked']);
            $response->assertUnprocessable();
        }

        $this->assertDatabaseCount('skills', 0);
    }

    #[Test]
    public function remote_imports_only_reach_explicitly_allowed_hosts(): void
    {
        config()->set('skills.imports.enabled', true);
        config()->set('skills.imports.sources.url.enabled', true);

        [, , $response] = $this->import(['url' => 'https://skills.example.com/index.json'], 'url');
        $response->assertUnprocessable();

        config()->set('skills.imports.sources.url.allowed_hosts', ['skills.example.com']);

        foreach ([
            'https://other.example.net/index.json',
            'http://skills.example.com/index.json',
            'https://user:secret@skills.example.com/index.json',
            'file:///etc/passwd',
            'https://skills.example.com.evil.test/index.json',
        ] as $url) {
            [, , $response] = $this->import(['url' => $url], 'url');
            $response->assertUnprocessable();
        }

        $this->assertDatabaseCount('skill_imports', 0);
    }

    #[Test]
    public function imports_require_owner_authorization(): void
    {
        $this->enableLocal();
        $user = $this->user();
        $workspace = $this->workspace();

        $this->actingAs($user)->postJson('/api/skill-imports', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'source' => 'local',
            'input' => ['root' => 'library'],
        ])->assertForbidden();

        $this->assertDatabaseCount('skills', 0);
    }
}
