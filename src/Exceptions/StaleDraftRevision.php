<?php

namespace Whilesmart\Skills\Exceptions;

class StaleDraftRevision extends SkillsException
{
    public function __construct(public readonly int $expected, public readonly int $current)
    {
        parent::__construct('The draft has changed since revision '.$expected.'; the current revision is '.$current.'.');
    }

    public function status(): int
    {
        return 409;
    }

    public function errors(): array
    {
        return ['draft_revision' => [$this->getMessage()], 'current_revision' => $this->current];
    }
}
