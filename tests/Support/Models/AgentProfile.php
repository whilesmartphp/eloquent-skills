<?php

namespace Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Skills\Traits\HasSkills;

class AgentProfile extends Model
{
    use HasSkills;

    protected $table = 'test_agent_profiles';

    protected $guarded = [];
}
