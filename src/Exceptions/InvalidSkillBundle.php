<?php

namespace Whilesmart\Skills\Exceptions;

class InvalidSkillBundle extends SkillsException
{
    public function status(): int
    {
        return 422;
    }

    public function errors(): array
    {
        return ['files' => [$this->getMessage()]];
    }

    public static function path(string $path): self
    {
        return new self('The file path "'.mb_substr($path, 0, 255).'" is not a safe relative path.');
    }

    public static function empty(): self
    {
        return new self('A skill needs at least a SKILL.md file.');
    }

    public static function missingRoot(): self
    {
        return new self('A skill needs a SKILL.md file at its root.');
    }

    public static function duplicate(string $path): self
    {
        return new self('The file "'.$path.'" is listed more than once.');
    }

    public static function content(string $path): self
    {
        return new self('The file "'.$path.'" has unreadable content.');
    }

    public static function encoding(string $path): self
    {
        return new self('The file "'.$path.'" has an unknown or invalid encoding.');
    }

    public static function tooManyFiles(int $limit): self
    {
        return new self("A skill can hold at most $limit files.");
    }

    public static function fileTooLarge(string $path, int $limit): self
    {
        return new self('The file "'.$path.'" is larger than '.$limit.' bytes.');
    }

    public static function bundleTooLarge(int $limit): self
    {
        return new self("A skill's files can total at most $limit bytes.");
    }

    public static function unreadable(string $reason): self
    {
        return new self('The skill could not be read: '.$reason);
    }

    public static function notSingle(): self
    {
        return new self('The files must hold exactly one skill.');
    }

    public static function invalidName(): self
    {
        return new self('SKILL.md needs a name of at most 64 lowercase letters, digits and single hyphens.');
    }

    public static function renameAfterPublication(): self
    {
        return new self('A skill cannot change its name after a version has been published.');
    }
}
