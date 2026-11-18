<?php

namespace Whilesmart\Skills\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Support\FileBundle;

class EncodedFiles implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $stored = json_decode((string) $value, true, 8, JSON_THROW_ON_ERROR);

        if (! is_array($stored)) {
            throw InvalidSkillBundle::content($key);
        }

        return FileBundle::decode($stored);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw InvalidSkillBundle::content($key);
        }

        return json_encode(FileBundle::encode($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
