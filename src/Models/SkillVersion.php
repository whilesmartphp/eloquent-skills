<?php

namespace Whilesmart\Skills\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Whilesmart\Skillpack\Skill as PortableSkill;
use Whilesmart\Skills\Casts\EncodedFiles;
use Whilesmart\Skills\Exceptions\ImmutableSkillVersion;
use Whilesmart\Skills\Exceptions\SkillVersionUnavailable;
use Whilesmart\Skills\Services\PortableSkills;
use Whilesmart\Skills\Support\SkillsConfig;

/**
 * @property int $id
 * @property string $uuid
 * @property int $skill_id
 * @property string $name
 * @property int $number
 * @property array<string, string> $files
 * @property string $content_hash
 * @property array|null $frontmatter
 * @property string $body
 * @property array|null $provenance
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property-read Skill|null $skill
 * @property-read SkillVersionAvailability|null $availability
 */
class SkillVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'skill_id',
        'name',
        'number',
        'files',
        'content_hash',
        'frontmatter',
        'body',
        'provenance',
        'created_by_type',
        'created_by_id',
    ];

    protected $hidden = ['files'];

    protected function casts(): array
    {
        return [
            'files' => EncodedFiles::class,
            'frontmatter' => 'array',
            'provenance' => 'array',
            'number' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return SkillsConfig::table('versions');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (SkillVersion $version) {
            $version->uuid ??= (string) Str::uuid();
        });

        static::updating(function () {
            throw new ImmutableSkillVersion;
        });

        static::deleting(function () {
            throw new ImmutableSkillVersion;
        });
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(SkillsConfig::model('skill'), 'skill_id');
    }

    public function availability(): HasOne
    {
        return $this->hasOne(SkillsConfig::model('availability'), 'skill_version_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->whereHas('skill')
            ->whereHas('availability', fn (Builder $availability) => $availability->where('status', SkillVersionAvailability::ACTIVE));
    }

    public function scopeOwnedBy(Builder $query, string $ownerType, int|string $ownerId, string $scopeKey): Builder
    {
        return $query->whereHas('skill', fn (Builder $skill) => $skill
            ->where('owner_type', $ownerType)
            ->where('owner_id', (string) $ownerId)
            ->where('scope_key', $scopeKey));
    }

    public function isAvailable(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return static::query()->whereKey($this->getKey())->available()->exists();
    }

    public function toPortableSkill(): PortableSkill
    {
        if (! $this->isAvailable()) {
            throw SkillVersionUnavailable::for($this->uuid);
        }

        $portable = app(PortableSkills::class)->forFolder($this->files, $this->name, $this->provenance ?? []);

        if (config('skills.verify_content_hash', true) && ! hash_equals($this->content_hash, $portable->contentHash())) {
            throw SkillVersionUnavailable::tampered($this->uuid);
        }

        return $portable;
    }
}
