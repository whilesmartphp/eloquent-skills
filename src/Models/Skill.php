<?php

namespace Whilesmart\Skills\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Whilesmart\Skills\Casts\EncodedFiles;
use Whilesmart\Skills\Support\SkillsConfig;

/**
 * @property int $id
 * @property string $public_id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $scope_key
 * @property string $name
 * @property string|null $active_name
 * @property string|null $description
 * @property int $draft_revision
 * @property array<string, string> $draft_files
 * @property array|null $draft_provenance
 * @property int $latest_version_number
 * @property array|null $metadata
 */
class Skill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'scope_key',
        'name',
        'active_name',
        'description',
        'draft_revision',
        'draft_files',
        'draft_provenance',
        'metadata',
    ];

    protected $hidden = ['draft_files'];

    protected $attributes = [
        'draft_revision' => 1,
        'latest_version_number' => 0,
    ];

    protected function casts(): array
    {
        return [
            'draft_files' => EncodedFiles::class,
            'draft_provenance' => 'array',
            'metadata' => 'array',
            'draft_revision' => 'integer',
            'latest_version_number' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return SkillsConfig::table('skills');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::creating(function (Skill $skill) {
            $skill->public_id ??= (string) Str::uuid();
            $skill->scope_key = SkillsConfig::scopeKey($skill->scope_key);
            $skill->active_name ??= $skill->name;
        });
    }

    protected function ownerId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : (string) $value,
            set: fn ($value) => $value === null ? null : (string) $value,
        );
    }

    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SkillsConfig::model('version'), 'skill_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(SkillsConfig::model('version'), 'skill_id')->ofMany('number', 'max');
    }

    public function scopeOwnedBy(Builder $query, string $ownerType, int|string $ownerId, ?string $scopeKey = null): Builder
    {
        return $query
            ->where($this->qualifyColumn('owner_type'), $ownerType)
            ->where($this->qualifyColumn('owner_id'), (string) $ownerId)
            ->where($this->qualifyColumn('scope_key'), SkillsConfig::scopeKey($scopeKey));
    }

    public function belongsToOwner(string $ownerType, int|string $ownerId, string $scopeKey): bool
    {
        return $this->owner_type === $ownerType
            && $this->owner_id === (string) $ownerId
            && $this->scope_key === $scopeKey;
    }
}
