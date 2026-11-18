<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Skills\Traits\HasSkills;

class Workspace extends Model
{
    use HasSkills;

    protected $table = 'test_workspaces';

    protected $guarded = [];
}
