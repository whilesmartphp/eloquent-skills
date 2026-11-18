<?php

namespace Whilesmart\Skills\Exceptions;

class SkillConflict extends SkillsException
{
    public static function name(string $name): self
    {
        return new self('A skill named "'.$name.'" already exists for this owner and scope.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errors(): array
    {
        return ['name' => [$this->getMessage()]];
    }
}
