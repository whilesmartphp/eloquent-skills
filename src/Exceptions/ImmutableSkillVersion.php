<?php

namespace Whilesmart\Skills\Exceptions;

class ImmutableSkillVersion extends SkillsException
{
    public function __construct()
    {
        parent::__construct('Published skill versions cannot be changed or deleted.');
    }

    public function status(): int
    {
        return 409;
    }
}
