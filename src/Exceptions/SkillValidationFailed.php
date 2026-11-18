<?php

namespace Whilesmart\Skills\Exceptions;

class SkillValidationFailed extends SkillsException
{
    /**
     * @param  list<array<string, mixed>>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('The skill does not meet the Agent Skills specification.');
    }

    public function status(): int
    {
        return 422;
    }

    public function errors(): array
    {
        return ['problems' => $this->problems];
    }
}
