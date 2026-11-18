<?php

namespace Whilesmart\Skills\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Whilesmart\Skills\Models\SkillVersion;
use Whilesmart\Skills\Models\SkillVersionAvailability;
use Whilesmart\Skills\Support\FileBundle;

/**
 * @mixin SkillVersion
 */
class SkillVersionResource extends JsonResource
{
    protected bool $withFiles = false;

    public function withFiles(bool $withFiles = true): static
    {
        $this->withFiles = $withFiles;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $availability = $this->availability;
        $skill = $this->skill;

        $data = [
            'id' => $this->uuid,
            'skill_id' => $skill?->uuid,
            'name' => $this->name,
            'number' => $this->number,
            'content_hash' => $this->content_hash,
            'frontmatter' => $this->frontmatter ?? (object) [],
            'provenance' => $this->provenance,
            'available' => $skill !== null && $availability instanceof SkillVersionAvailability && $availability->isActive(),
            'availability' => [
                'status' => $availability?->status ?? SkillVersionAvailability::REVOKED,
                'reason' => $availability?->reason,
                'revoked_at' => $availability?->revoked_at?->toIso8601String(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($this->withFiles) {
            $data['body'] = $this->body;
            $data['files'] = FileBundle::present($this->files);
        }

        return $data;
    }
}
