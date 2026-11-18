<?php

namespace Whilesmart\Skills\Exceptions;

class SkillAccessDenied extends SkillsException
{
    public function __construct(string $message = 'This action is unauthorized.')
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 403;
    }
}
