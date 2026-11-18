<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UuidUser extends Authenticatable
{
    use HasUuids;

    protected $table = 'test_uuid_users';

    protected $guarded = [];
}
