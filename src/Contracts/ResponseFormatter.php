<?php

namespace Whilesmart\Skills\Contracts;

use Illuminate\Http\JsonResponse;

interface ResponseFormatter
{
    public function success(mixed $data = null, int $status = 200, array $meta = []): JsonResponse;

    public function error(string $message, int $status, array $errors = []): JsonResponse;
}
