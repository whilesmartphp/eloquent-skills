<?php

namespace Whilesmart\Skills\Exceptions;

class SkillBindingRejected extends SkillsException
{
    public static function unsaved(): self
    {
        return new self('Skills can only be bound to a saved model.');
    }

    public static function notSkillable(): self
    {
        return new self('The model does not use the HasSkills trait.');
    }

    public static function ownerMismatch(): self
    {
        return new self('The skill does not belong to the same owner and scope as the model.');
    }

    public function status(): int
    {
        return 422;
    }
}
