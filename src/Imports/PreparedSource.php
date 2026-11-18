<?php

namespace Whilesmart\Skills\Imports;

use Whilesmart\Skillpack\Contracts\SkillSource;
use Whilesmart\Skillpack\LoadOptions;

final class PreparedSource
{
    /**
     * @param  list<string>  $redactions  Text, such as server paths, removed from stored error messages.
     */
    public function __construct(
        public readonly string|SkillSource $source,
        public readonly LoadOptions $options,
        public readonly string $reference,
        public readonly bool $recordSourceProvenance = true,
        public readonly array $redactions = [],
    ) {}

    public function redact(string $message): string
    {
        $redactions = array_values(array_filter($this->redactions, fn ($value) => is_string($value) && $value !== ''));

        return $redactions === [] ? $message : str_replace($redactions, '[source]', $message);
    }
}
