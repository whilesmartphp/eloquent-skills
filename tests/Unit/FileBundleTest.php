<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Support\FileBundle;

class FileBundleTest extends TestCase
{
    public static function unsafePaths(): array
    {
        return [
            'absolute' => ['/etc/passwd'],
            'parent' => ['../SKILL.md'],
            'nested parent' => ['references/../../x.md'],
            'dot segment' => ['./SKILL.md'],
            'empty segment' => ['references//x.md'],
            'backslash' => ['references\\x.md'],
            'drive' => ['C:/x.md'],
            'control' => ["refer\nences/x.md"],
            'trailing slash' => ['references/'],
            'padded segment' => [' references/x.md'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('unsafePaths')]
    public function it_rejects_unsafe_paths(string $path): void
    {
        $this->expectException(InvalidSkillBundle::class);

        FileBundle::normalizePath($path);
    }

    #[Test]
    public function it_accepts_nested_relative_paths(): void
    {
        $this->assertSame('references/deep/grids.md', FileBundle::normalizePath('references/deep/grids.md'));
    }

    #[Test]
    public function it_requires_a_root_skill_file(): void
    {
        $this->expectException(InvalidSkillBundle::class);

        FileBundle::check(['references/x.md' => 'x']);
    }

    #[Test]
    public function it_rejects_a_file_that_is_also_a_folder(): void
    {
        $this->expectException(InvalidSkillBundle::class);

        FileBundle::check(['SKILL.md' => 'x', 'references' => 'x', 'references/a.md' => 'y']);
    }

    #[Test]
    public function it_enforces_file_count_and_size_limits(): void
    {
        config()->set('skills.limits.max_files', 2);

        try {
            FileBundle::check(['SKILL.md' => 'a', 'b.md' => 'b', 'c.md' => 'c']);
            $this->fail('The file count limit was not enforced.');
        } catch (InvalidSkillBundle) {
        }

        config()->set('skills.limits.max_files', 10);
        config()->set('skills.limits.max_file_bytes', 4);

        try {
            FileBundle::check(['SKILL.md' => 'abcde']);
            $this->fail('The file size limit was not enforced.');
        } catch (InvalidSkillBundle) {
        }

        config()->set('skills.limits.max_file_bytes', 10);
        config()->set('skills.limits.max_bundle_bytes', 12);

        $this->expectException(InvalidSkillBundle::class);

        FileBundle::check(['SKILL.md' => 'abcdefgh', 'b.md' => 'abcdefgh']);
    }

    #[Test]
    public function it_round_trips_arbitrary_bytes_through_json_storage(): void
    {
        $files = [
            'SKILL.md' => "---\nname: x\n---\n\n  body with trailing space  \n",
            'assets/logo.png' => "\x89PNG\r\n\x1a\n\x00\xff\xfe",
        ];

        $encoded = FileBundle::encode($files);

        $this->assertSame('utf8', $encoded['SKILL.md']['encoding']);
        $this->assertSame('base64', $encoded['assets/logo.png']['encoding']);

        $json = json_encode($encoded, JSON_THROW_ON_ERROR);

        $this->assertSame($files, FileBundle::decode(json_decode($json, true)));
    }

    #[Test]
    public function it_rejects_invalid_base64(): void
    {
        $this->expectException(InvalidSkillBundle::class);

        FileBundle::decodeContent('a.bin', '***', 'base64');
    }
}
