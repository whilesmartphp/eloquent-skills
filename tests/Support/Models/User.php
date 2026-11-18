<?php

namespace Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'test_users';

    protected $guarded = [];
}
