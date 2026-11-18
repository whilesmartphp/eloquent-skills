<?php

namespace Whilesmart\Skills\Exceptions;

class SkillVersionUnavailable extends SkillsException
{
    public static function for(string $id): self
    {
        return new self('The skill version "'.mb_substr($id, 0, 64).'" is not available for this owner and scope.');
    }

    public static function tampered(string $id): self
    {
        return new self('The skill version "'.$id.'" no longer matches its content hash.');
    }

    public function status(): int
    {
        return 404;
    }
}
