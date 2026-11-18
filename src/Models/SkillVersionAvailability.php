<?php

namespace Whilesmart\Skills\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Whilesmart\Skills\Support\SkillsConfig;

/**
 * @property int $id
 * @property int $skill_version_id
 * @property string $status
 * @property string|null $reason
 * @property Carbon|null $revoked_at
 */
class SkillVersionAvailability extends Model
{
    public const ACTIVE = 'active';

    public const REVOKED = 'revoked';

    protected $fillable = [
        'skill_version_id',
        'status',
        'reason',
        'revoked_at',
        'changed_by_type',
        'changed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return SkillsConfig::table('availabilities');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SkillsConfig::model('version'), 'skill_version_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
