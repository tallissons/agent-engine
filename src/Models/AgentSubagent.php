<?php

namespace BuildAgents\AgentEngine\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AgentSubagent extends Pivot
{
    public $incrementing = true;

    public function getTable(): string
    {
        // respeita setTable()
        return $this->table ?? config('agent-engine.tables.subagents', 'agent_subagents');
    }
}
