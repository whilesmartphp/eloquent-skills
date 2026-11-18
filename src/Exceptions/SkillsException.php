<?php

namespace Whilesmart\Skills\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Whilesmart\Skills\Contracts\ResponseFormatter;

abstract class SkillsException extends RuntimeException
{
    abstract public function status(): int;

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return [];
    }

    public function render(): JsonResponse
    {
        return app(ResponseFormatter::class)->error($this->getMessage(), $this->status(), $this->errors());
    }
}
