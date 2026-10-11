<?php

namespace BuildAgents\AgentEngine\Tests\Fixtures;

use BuildAgents\AgentEngine\Concerns\HasAgents;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasAgents;

    protected $guarded = [];
}
