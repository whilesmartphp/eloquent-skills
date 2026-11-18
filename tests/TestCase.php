<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\GrantingOwnerAuthorizer;
use Tests\Support\Models\User;
use Tests\Support\Models\Workspace;
use Whilesmart\OwnerAccess\Contracts\OwnerAuthorizer;
use Whilesmart\OwnerAccess\OwnerAccessServiceProvider;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\SkillsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected GrantingOwnerAuthorizer $owners;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owners = new GrantingOwnerAuthorizer;
        $this->app->instance(OwnerAuthorizer::class, $this->owners);
    }

    protected function getPackageProviders($app): array
    {
        return [
            OwnerAccessServiceProvider::class,
            SkillsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('skills.route_middleware', ['api']);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * @return array<string, string>
     */
    protected function fixture(string $name): array
    {
        $root = __DIR__.'/fixtures/skills/'.$name;
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile()) {
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, string>  $files
     * @return list<array{path: string, content: string, encoding: string}>
     */
    protected function payloadFiles(array $files): array
    {
        $payload = [];

        foreach ($files as $path => $bytes) {
            $text = mb_check_encoding($bytes, 'UTF-8');
            $payload[] = [
                'path' => (string) $path,
                'content' => $text ? $bytes : base64_encode($bytes),
                'encoding' => $text ? 'utf8' : 'base64',
            ];
        }

        return $payload;
    }

    protected function user(string $name = 'Ada'): User
    {
        return User::create(['name' => $name]);
    }

    protected function workspace(string $name = 'Studio'): Workspace
    {
        return Workspace::create(['name' => $name]);
    }

    protected function grant(Authenticatable $actor, Model $owner): void
    {
        $this->owners->grant($actor, $owner);
    }

    protected function createSkillVia(Authenticatable $actor, Model $owner, string $fixture = 'page-layout', ?string $scope = null): TestResponse
    {
        $previousScope = config('skills.default_scope_key');
        config(['skills.default_scope_key' => $scope ?? $previousScope]);
        try {
            return $this->actingAs($actor)->postJson('/api/skills', array_filter([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'scope_key' => $scope,
                'files' => $this->payloadFiles($this->fixture($fixture)),
            ], fn ($value) => $value !== null));
        } finally {
            config(['skills.default_scope_key' => $previousScope]);
        }
    }

    protected function publishedVersion(Authenticatable $actor, Model $owner, string $fixture = 'page-layout', ?string $scope = null): SkillVersion
    {
        $previousScope = config('skills.default_scope_key');
        config(['skills.default_scope_key' => $scope ?? $previousScope]);
        try {
            $created = $this->createSkillVia($actor, $owner, $fixture, $scope)->assertCreated();
            $skillId = $created->json('data.id');

            $published = $this->actingAs($actor)
                ->postJson("/api/skills/{$skillId}/publish", ['draft_revision' => $created->json('data.draft_revision')])
                ->assertCreated();

            return SkillVersion::query()->where('uuid', $published->json('data.id'))->firstOrFail();
        } finally {
            config(['skills.default_scope_key' => $previousScope]);
        }
    }

    protected function skill(string $uuid): Skill
    {
        return Skill::withTrashed()->where('uuid', $uuid)->firstOrFail();
    }
}
