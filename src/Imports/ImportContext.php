<?php

namespace Whilesmart\Skills\Imports;

use Illuminate\Contracts\Auth\Authenticatable;
use Whilesmart\Skillpack\LoadOptions;
use Whilesmart\Skills\Support\SkillsConfig;

final class ImportContext
{
    public function __construct(
        public readonly string $ownerType,
        public readonly int|string $ownerId,
        public readonly string $scopeKey,
        public readonly ?Authenticatable $actor = null,
    ) {}

    /**
     * @param  list<string>  $allowedHosts
     * @param  list<string>  $allowedRoots
     */
    public function options(array $allowedHosts = [], array $allowedRoots = []): LoadOptions
    {
        return new LoadOptions(
            allowedHosts: array_values($allowedHosts),
            allowedRoots: array_values($allowedRoots),
            maxDownloadBytes: SkillsConfig::limit('max_download_bytes'),
            maxExtractedBytes: SkillsConfig::limit('max_extracted_bytes'),
            maxFiles: SkillsConfig::limit('max_files') * SkillsConfig::limit('max_skills_per_import'),
            maxSkills: SkillsConfig::limit('max_skills_per_import'),
            timeoutSeconds: SkillsConfig::limit('timeout_seconds'),
            allowPrivateAddresses: false,
        );
    }
}
