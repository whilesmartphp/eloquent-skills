<?php

namespace Whilesmart\Skills\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Whilesmart\Skills\Models\SkillImport;

/**
 * @mixin SkillImport
 */
class SkillImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'scope_key' => $this->scope_key,
            'source' => $this->source,
            'reference' => $this->reference,
            'status' => $this->status,
            'skills' => $this->result['skills'] ?? [],
            'error' => $this->error,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
