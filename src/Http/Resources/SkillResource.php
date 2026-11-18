<?php

namespace Whilesmart\Skills\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Whilesmart\Skills\Models\Skill;
use Whilesmart\Skills\Support\FileBundle;

/**
 * @mixin Skill
 */
class SkillResource extends JsonResource
{
    protected bool $withFiles = false;

    public function withFiles(bool $withFiles = true): static
    {
        $this->withFiles = $withFiles;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->uuid,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'scope_key' => $this->scope_key,
            'name' => $this->name,
            'description' => $this->description,
            'draft_revision' => $this->draft_revision,
            'latest_version_number' => $this->latest_version_number,
            'metadata' => $this->metadata ?? (object) [],
            'provenance' => $this->draft_provenance,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->withFiles) {
            $data['files'] = FileBundle::present($this->draft_files);
        }

        return $data;
    }
}
