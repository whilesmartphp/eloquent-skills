<?php

namespace Whilesmart\Skills\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Whilesmart\Skills\Support\SkillsConfig;

/**
 * @property int $id
 * @property string $skillable_type
 * @property string $skillable_id
 * @property int $skill_id
 * @property int $skill_version_id
 * @property array|null $metadata
 * @property-read Skill|null $skill
 * @property-read SkillVersion|null $version
 */
class SkillBinding extends Model
{
    protected $fillable = [
        'skillable_type',
        'skillable_id',
        'skill_id',
        'skill_version_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return SkillsConfig::table('bindings');
    }

    protected function skillableId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : (string) $value,
            set: fn ($value) => $value === null ? null : (string) $value,
        );
    }

    public function skillable(): MorphTo
    {
        return $this->morphTo('skillable');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(SkillsConfig::model('skill'), 'skill_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SkillsConfig::model('version'), 'skill_version_id');
    }

    public function isAvailable(): bool
    {
        $version = $this->version()->first();

        return $version instanceof SkillVersion
            && (int) $version->skill_id === (int) $this->skill_id
            && $version->isAvailable();
    }
}
