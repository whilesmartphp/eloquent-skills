<?php

namespace Whilesmart\Skills\Exceptions;

class ImportSourceRejected extends SkillsException
{
    public static function disabled(): self
    {
        return new self('Skill imports are not enabled.');
    }

    public static function unknown(string $source): self
    {
        return new self('The skill source "'.mb_substr($source, 0, 64).'" is not available.');
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function status(): int
    {
        return 422;
    }

    public function errors(): array
    {
        return ['source' => [$this->getMessage()]];
    }
}
