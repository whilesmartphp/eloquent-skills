<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Skills\Models\Skill;

class SkillDraftApiTest extends TestCase
{
    #[Test]
    public function it_creates_a_draft_from_raw_files_and_keeps_bytes_exact(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);

        $files = $this->fixture('page-layout');
        $files['assets/logo.png'] = "\x89PNG\r\n\x1a\n\x00\xff";

        $response = $this->actingAs($user)->postJson('/api/skills', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'files' => $this->payloadFiles($files),
            'metadata' => ['team' => 'design'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'page-layout')
            ->assertJsonPath('data.scope_key', 'default')
            ->assertJsonPath('data.owner_id', (string) $workspace->getKey())
            ->assertJsonPath('data.draft_revision', 1)
            ->assertJsonPath('data.latest_version_number', 0)
            ->assertJsonPath('data.metadata.team', 'design');

        $skill = $this->skill($response->json('data.id'));

        $this->assertSame($files['SKILL.md'], $skill->draft_files['SKILL.md']);
        $this->assertStringEndsWith("\n", $skill->draft_files['SKILL.md']);
        $this->assertSame("\x89PNG\r\n\x1a\n\x00\xff", $skill->draft_files['assets/logo.png']);

        $logo = collect($response->json('data.files'))->firstWhere('path', 'assets/logo.png');
        $this->assertSame('base64', $logo['encoding']);
        $this->assertSame(base64_encode("\x89PNG\r\n\x1a\n\x00\xff"), $logo['content']);
    }

    #[Test]
    public function it_accepts_multipart_uploads(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $fixture = $this->fixture('data-modelling');

        $this->actingAs($user)->post('/api/skills', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => (string) $workspace->getKey(),
            'uploads' => [UploadedFile::fake()->createWithContent('SKILL.md', $fixture['SKILL.md'])],
            'paths' => ['SKILL.md'],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'data-modelling');

        $this->assertSame($fixture['SKILL.md'], Skill::query()->firstOrFail()->draft_files['SKILL.md']);
    }

    #[Test]
    public function it_rejects_unsafe_paths_missing_roots_and_oversized_bundles(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $owner = ['owner_type' => $workspace->getMorphClass(), 'owner_id' => $workspace->getKey()];
        $skill = $this->fixture('data-modelling')['SKILL.md'];

        $this->actingAs($user)->postJson('/api/skills', $owner + ['files' => [
            ['path' => 'SKILL.md', 'content' => $skill],
            ['path' => '../escape.md', 'content' => 'x'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->actingAs($user)->postJson('/api/skills', $owner + ['files' => [
            ['path' => 'references/only.md', 'content' => 'x'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->actingAs($user)->postJson('/api/skills', $owner + ['files' => [
            ['path' => 'SKILL.md', 'content' => $skill],
            ['path' => 'SKILL.md', 'content' => $skill],
        ]])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/skills', $owner + ['files' => [
            ['path' => 'SKILL.md', 'content' => $skill, 'mode' => '0755'],
        ]])->assertUnprocessable();

        config()->set('skills.limits.max_bundle_bytes', 32);

        $this->actingAs($user)->postJson('/api/skills', $owner + ['files' => [
            ['path' => 'SKILL.md', 'content' => $skill],
        ]])->assertUnprocessable();

        $this->assertDatabaseCount('skills', 0);
    }

    #[Test]
    public function it_rejects_a_skill_without_a_valid_name_and_duplicate_names(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);

        $this->actingAs($user)->postJson('/api/skills', [
            'owner_type' => $workspace->getMorphClass(),
            'owner_id' => $workspace->getKey(),
            'files' => [['path' => 'SKILL.md', 'content' => "---\nname: Not Valid\ndescription: x\n---\nBody\n"]],
        ])->assertUnprocessable();

        $this->createSkillVia($user, $workspace)->assertCreated();
        $this->createSkillVia($user, $workspace)->assertStatus(409);
        $this->createSkillVia($user, $workspace, 'page-layout', 'staging')->assertCreated();

        $this->assertDatabaseCount('skills', 2);
    }

    #[Test]
    public function edits_are_revision_checked(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $id = $this->createSkillVia($user, $workspace)->assertCreated()->json('data.id');

        $files = $this->fixture('page-layout');
        $files['references/grids.md'] = "# Grids\n\nUse an 8 column grid.\n";

        $this->actingAs($user)->patchJson("/api/skills/{$id}", [
            'draft_revision' => 1,
            'files' => $this->payloadFiles($files),
        ])->assertOk()->assertJsonPath('data.draft_revision', 2);

        $this->actingAs($user)->patchJson("/api/skills/{$id}", [
            'draft_revision' => 1,
            'metadata' => ['late' => true],
        ])->assertStatus(409)->assertJsonPath('errors.current_revision', 2);

        $this->actingAs($user)->patchJson("/api/skills/{$id}", [
            'draft_revision' => 2,
            'metadata' => ['tier' => 'gold'],
        ])->assertOk()->assertJsonPath('data.draft_revision', 3)->assertJsonPath('data.metadata.tier', 'gold');

        $skill = $this->skill($id);
        $this->assertSame("# Grids\n\nUse an 8 column grid.\n", $skill->draft_files['references/grids.md']);
    }

    #[Test]
    public function it_reports_draft_and_ad_hoc_validation_without_storing(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $id = $this->createSkillVia($user, $workspace, 'bad-skill')->assertCreated()->json('data.id');

        $report = $this->actingAs($user)->getJson("/api/skills/{$id}/validation")->assertOk();
        $report->assertJsonPath('data.valid', false);
        $codes = array_column($report->json('data.problems'), 'code');
        $this->assertContains('unknown-field', $codes);
        $this->assertContains('description-brackets', $codes);
        $this->assertContains('link-missing', $codes);

        $this->actingAs($user)->postJson('/api/skills/validate', [
            'files' => $this->payloadFiles($this->fixture('page-layout')),
        ])->assertOk()->assertJsonPath('data.valid', true)->assertJsonPath('data.name', 'page-layout');

        $this->assertDatabaseCount('skills', 1);
    }

    #[Test]
    public function deleting_a_skill_hides_it_and_frees_its_name(): void
    {
        $user = $this->user();
        $workspace = $this->workspace();
        $this->grant($user, $workspace);
        $id = $this->createSkillVia($user, $workspace)->json('data.id');

        $this->actingAs($user)->deleteJson("/api/skills/{$id}")->assertOk();
        $this->actingAs($user)->getJson("/api/skills/{$id}")->assertNotFound();
        $this->assertSoftDeleted('skills', ['public_id' => $id]);

        $this->createSkillVia($user, $workspace)->assertCreated();
    }

    #[Test]
    public function index_lists_only_accessible_skills_with_filters(): void
    {
        $user = $this->user();
        $mine = $this->workspace('Mine');
        $theirs = $this->workspace('Theirs');
        $other = $this->user('Other');
        $this->grant($user, $mine);
        $this->grant($other, $theirs);

        $this->createSkillVia($user, $mine)->assertCreated();
        $this->createSkillVia($user, $mine, 'data-modelling', 'staging')->assertCreated();
        $this->createSkillVia($other, $theirs)->assertCreated();

        $this->actingAs($user)->getJson('/api/skills')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 2);

        $this->actingAs($user)->getJson('/api/skills?scope_key=staging')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.name', 'data-modelling');

        $this->actingAs($user)->getJson('/api/skills?q=layout')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->assertArrayNotHasKey('files', $this->actingAs($user)->getJson('/api/skills')->json('data.data.0'));
    }
}
