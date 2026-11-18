<?php

namespace Whilesmart\Skills\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class Actor
{
    public static function type(?Authenticatable $actor): ?string
    {
        if ($actor === null) {
            return null;
        }

        return $actor instanceof Model ? $actor->getMorphClass() : $actor::class;
    }

    public static function id(?Authenticatable $actor): ?string
    {
        $id = $actor?->getAuthIdentifier();

        return is_int($id) || is_string($id) ? (string) $id : null;
    }

    /**
     * @return array<string, string|null>
     */
    public static function columns(?Authenticatable $actor, string $prefix): array
    {
        return [
            $prefix.'_type' => self::type($actor),
            $prefix.'_id' => self::id($actor),
        ];
    }
}
