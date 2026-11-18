<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Skills\Traits\HasSkills;

class UuidWorkspace extends Model
{
    use HasSkills;
    use HasUuids;

    protected $table = 'test_uuid_workspaces';

    protected $guarded = [];
}
