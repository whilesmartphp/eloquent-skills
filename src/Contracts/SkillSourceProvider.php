<?php

namespace Whilesmart\Skills\Contracts;

use Whilesmart\Skills\Imports\ImportContext;
use Whilesmart\Skills\Imports\PreparedSource;

interface SkillSourceProvider
{
    public function enabled(): bool;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @return array<string, mixed>
     */
    public function describe(): array;

    /**
     * @param  array<string, mixed>  $input
     */
    public function prepare(array $input, ImportContext $context): PreparedSource;
}
