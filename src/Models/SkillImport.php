<?php

namespace Whilesmart\Skills\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Whilesmart\Skills\Support\SkillsConfig;

/**
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property string $owner_id
 * @property string $scope_key
 * @property string $source
 * @property string $reference
 * @property string $status
 * @property array|null $result
 * @property string|null $error
 * @property Carbon|null $completed_at
 */
class SkillImport extends Model
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'owner_type',
        'owner_id',
        'scope_key',
        'source',
        'reference',
        'status',
        'result',
        'error',
        'actor_type',
        'actor_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return SkillsConfig::table('imports');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (SkillImport $import) {
            $import->uuid ??= (string) Str::uuid();
            $import->scope_key = SkillsConfig::scopeKey($import->scope_key);
        });
    }

    protected function ownerId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : (string) $value,
            set: fn ($value) => $value === null ? null : (string) $value,
        );
    }
}
